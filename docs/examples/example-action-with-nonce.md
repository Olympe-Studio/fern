# Example: Action with Nonce Validation

This example demonstrates a secure form submission with nonce validation using the `#[Nonce]` attribute.

## Use Case

You have a contact form that needs to be protected against CSRF attacks using WordPress nonces.

## File Structure

```
src/App/Controllers/ContactPageController.php
resources/src/pages/ContactPage.astro
resources/src/components/ContactForm.tsx
```

## Controller Implementation

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Controllers\ViewController;
use Fern\Core\Services\Actions\Attributes\Nonce;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Views\Views;
use Timber\Timber;

class ContactPageController extends ViewController implements Controller {
    public static string $handle = '12';

    /**
     * Handle the contact page request
     *
     * @param Request $request The HTTP request
     *
     * @return Reply The HTTP reply with rendered view
     */
    public function handle(Request $request): Reply {
        $post = Timber::get_post();

        return new Reply(200, Views::render('ContactPage', [
            'title' => $post->title(),
            'content' => $post->content(),
            'nonces' => [
                'contact_form' => wp_create_nonce('contact_form'),
            ],
        ]));
    }

    /**
     * Handle contact form submission
     *
     * @param Request $request The HTTP request
     *
     * @return Reply The HTTP reply with submission result
     */
    #[Nonce('contact_form')]
    public function submitContact(Request $request): Reply {
        $action = $request->getAction();

        $name = sanitize_text_field($action->get('name', ''));
        $email = sanitize_email($action->get('email', ''));
        $message = sanitize_textarea_field($action->get('message', ''));

        if (!$this->validateContactForm($name, $email, $message)) {
            return new Reply(400, [
                'success' => false,
                'message' => 'Please fill in all required fields.',
            ]);
        }

        $sent = $this->sendContactEmail($name, $email, $message);

        if (!$sent) {
            return new Reply(500, [
                'success' => false,
                'message' => 'Failed to send message. Please try again.',
            ]);
        }

        return new Reply(200, [
            'success' => true,
            'message' => 'Thank you! Your message has been sent.',
        ]);
    }

    /**
     * Validate contact form data
     *
     * @param string $name    The contact name
     * @param string $email   The contact email
     * @param string $message The contact message
     *
     * @return bool True if valid, false otherwise
     */
    private function validateContactForm(string $name, string $email, string $message): bool {
        if (empty($name) || empty($email) || empty($message)) {
            return false;
        }

        if (!is_email($email)) {
            return false;
        }

        if (strlen($message) < 10) {
            return false;
        }

        return true;
    }

    /**
     * Send contact email to site admin
     *
     * @param string $name    The contact name
     * @param string $email   The contact email
     * @param string $message The contact message
     *
     * @return bool True if sent successfully
     */
    private function sendContactEmail(string $name, string $email, string $message): bool {
        $to = get_option('admin_email');
        $subject = sprintf('[%s] New Contact Form Submission', get_bloginfo('name'));
        $body = "Name: {$name}\nEmail: {$email}\n\nMessage:\n{$message}";
        $headers = ["Reply-To: {$email}"];

        return wp_mail($to, $subject, $body, $headers);
    }
}
```

## Frontend Component (SolidJS/TypeScript)

```tsx
// resources/src/components/ContactForm.tsx
import { createSignal } from 'solid-js';
import { callAction } from '@ferndev/core';

interface ContactFormProps {
  nonce: string;
}

export default function ContactForm(props: ContactFormProps) {
  const [name, setName] = createSignal('');
  const [email, setEmail] = createSignal('');
  const [message, setMessage] = createSignal('');
  const [loading, setLoading] = createSignal(false);
  const [result, setResult] = createSignal<string | null>(null);
  const [error, setError] = createSignal<string | null>(null);

  const handleSubmit = async (e: Event) => {
    e.preventDefault();
    setLoading(true);
    setResult(null);
    setError(null);

    const { data, error: actionError } = await callAction(
      'submitContact',
      {
        name: name(),
        email: email(),
        message: message(),
      },
      props.nonce
    );

    setLoading(false);

    if (actionError) {
      setError(actionError.message);
      return;
    }

    if (data?.success) {
      setResult(data.message);
      setName('');
      setEmail('');
      setMessage('');
    } else {
      setError(data?.message || 'An error occurred');
    }
  };

  return (
    <form onSubmit={handleSubmit} class="contact-form">
      {result() && (
        <div class="alert alert-success">{result()}</div>
      )}

      {error() && (
        <div class="alert alert-error">{error()}</div>
      )}

      <div class="form-group">
        <label for="name">Name *</label>
        <input
          id="name"
          type="text"
          value={name()}
          onInput={(e) => setName(e.currentTarget.value)}
          required
          disabled={loading()}
        />
      </div>

      <div class="form-group">
        <label for="email">Email *</label>
        <input
          id="email"
          type="email"
          value={email()}
          onInput={(e) => setEmail(e.currentTarget.value)}
          required
          disabled={loading()}
        />
      </div>

      <div class="form-group">
        <label for="message">Message *</label>
        <textarea
          id="message"
          rows={5}
          value={message()}
          onInput={(e) => setMessage(e.currentTarget.value)}
          required
          disabled={loading()}
        />
      </div>

      <button type="submit" disabled={loading()}>
        {loading() ? 'Sending...' : 'Send Message'}
      </button>
    </form>
  );
}
```

## Frontend Template (Astro)

```astro
---
// resources/src/pages/ContactPage.astro
import ContactForm from '../components/ContactForm';

interface Props {
  title: string;
  content: string;
  nonces: {
    contact_form: string;
  };
}

const { title, content, nonces } = Astro.props;
---

<Layout title={title}>
  <main>
    <h1>{title}</h1>
    <div set:html={content} />

    <ContactForm client:load nonce={nonces.contact_form} />
  </main>
</Layout>
```

## How It Works

1. **Controller Renders Page**: The `handle()` method generates a nonce and passes it to the view
2. **Nonce Sent to Frontend**: The nonce is available as `nonces.contact_form` in the template
3. **Form Submission**: When the form is submitted, `callAction()` sends the nonce along with form data
4. **Nonce Validation**: The `#[Nonce('contact_form')]` attribute automatically validates the nonce before executing `submitContact()`
5. **Automatic Rejection**: If the nonce is invalid, the action returns a 403 error automatically
6. **Action Executes**: If validation passes, the action processes the form and sends the email

## Security Features

1. **CSRF Protection**: Nonce prevents cross-site request forgery
2. **Input Sanitization**: All user input is sanitized using WordPress functions
3. **Email Validation**: Uses `is_email()` for proper email validation
4. **Length Validation**: Ensures message has minimum length
5. **Error Messages**: Generic error messages don't leak system information

## Key Points

- The `#[Nonce]` attribute parameter must match the nonce name used in `wp_create_nonce()`
- Nonces are automatically validated before the action method executes
- Failed nonce validation returns a 403 Forbidden response automatically
- Always sanitize user input even after nonce validation
- Return appropriate HTTP status codes (400 for validation, 500 for server errors)

## Testing

1. Visit the contact page
2. Fill in the form and submit
3. Check that the email is sent to the admin email
4. Try submitting with an expired or invalid nonce (should fail)
5. Try submitting with missing fields (should show validation error)
