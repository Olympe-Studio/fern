# Frontend Integration

> TypeScript libraries for frontend-backend communication

## Overview

Fern provides two frontend packages for communicating with PHP controllers:

- **@ferndev/core** - Core action calling functionality
- **@ferndev/woo** - WooCommerce cart integration with reactive state

Both packages are designed for use with Astro/SolidJS but work with any modern JavaScript framework.

---

## @ferndev/core

The core package provides `callAction()` for making authenticated requests to Fern controller actions.

### Installation

```bash
bun add @ferndev/core
```

### callAction

Makes an authenticated action request to a Fern controller.

```typescript
import { callAction } from '@ferndev/core';

const { data, error, status } = await callAction<ResponseType>(
    action,
    args,
    nonce,
    options
);
```

**Type Definition:**
```typescript
function callAction<T>(
    action: string,
    args?: Record<string, any> | FormData,
    nonce?: string,
    options?: { timeout?: number }
): Promise<{
    data?: T;
    error?: { message: string; status?: number };
    status: 'ok' | 'error';
}>;
```

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `action` | `string` | Yes | Action method name on controller |
| `args` | `object \| FormData` | No | Arguments to pass to action |
| `nonce` | `string` | No | CSRF nonce token for security |
| `options` | `object` | No | Configuration options |

**Options:**
| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `timeout` | `number` | `30000` | Request timeout in milliseconds |

**Returns:**
```typescript
{
    data?: T;          // Response data on success
    error?: {
        message: string;
        status?: number;
    };                 // Error details on failure
    status: 'ok' | 'error';
}
```

### Basic Usage

```typescript
// Simple action call
const { data, error } = await callAction('getProducts');

if (error) {
    console.error(error.message);
    return;
}

console.log(data);
```

### With Arguments

```typescript
const { data, error } = await callAction('searchProducts', {
    query: 'widget',
    category: 'tools',
    page: 1
});
```

### With Nonce (Secure Actions)

```typescript
// Get nonce from PHP (passed via Views::render)
const nonce = props.nonces.submit_form;

const { data, error } = await callAction('submitForm', {
    name: 'John Doe',
    email: 'john@example.com',
    message: 'Hello!'
}, nonce);
```

### With Type Safety

```typescript
interface LoginResponse {
    success: boolean;
    user: {
        id: number;
        name: string;
        email: string;
    };
    redirect_url: string;
}

const { data, error } = await callAction<LoginResponse>('login', {
    email: 'user@example.com',
    password: 'secret'
}, nonce);

if (data?.success) {
    window.location.href = data.redirect_url;
}
```

### File Uploads (FormData)

```typescript
const formData = new FormData();
formData.append('file', fileInput.files[0]);
formData.append('title', 'My Document');

const { data, error } = await callAction('uploadFile', formData, nonce);

if (data?.url) {
    console.log('Uploaded:', data.url);
}
```

### Custom Timeout

```typescript
// Long-running operation
const { data, error } = await callAction(
    'generateReport',
    { type: 'annual' },
    nonce,
    { timeout: 120000 } // 2 minutes
);
```

### Error Handling

```typescript
const { data, error, status } = await callAction('processOrder', orderData, nonce);

if (status === 'error') {
    switch (error?.status) {
        case 400:
            showValidationError(error.message);
            break;
        case 403:
            showAccessDenied();
            break;
        case 408:
            showTimeoutError();
            break;
        default:
            showGenericError(error?.message || 'Something went wrong');
    }
    return;
}

// Success
showConfirmation(data);
```

### Complete SolidJS Example

```tsx
import { createSignal } from 'solid-js';
import { callAction } from '@ferndev/core';

interface Props {
    nonce: string;
}

interface SubscribeResponse {
    success: boolean;
    message: string;
}

export default function NewsletterForm(props: Props) {
    const [email, setEmail] = createSignal('');
    const [loading, setLoading] = createSignal(false);
    const [message, setMessage] = createSignal('');
    const [error, setError] = createSignal('');

    const handleSubmit = async (e: Event) => {
        e.preventDefault();
        setLoading(true);
        setMessage('');
        setError('');

        const { data, error: err } = await callAction<SubscribeResponse>(
            'subscribeNewsletter',
            { email: email() },
            props.nonce
        );

        setLoading(false);

        if (err) {
            setError(err.message);
            return;
        }

        if (data?.success) {
            setMessage(data.message);
            setEmail('');
        } else {
            setError(data?.message || 'Subscription failed');
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            {message() && <div class="success">{message()}</div>}
            {error() && <div class="error">{error()}</div>}

            <input
                type="email"
                value={email()}
                onInput={(e) => setEmail(e.currentTarget.value)}
                placeholder="Enter your email"
                required
                disabled={loading()}
            />

            <button type="submit" disabled={loading()}>
                {loading() ? 'Subscribing...' : 'Subscribe'}
            </button>
        </form>
    );
}
```

---

## @ferndev/woo

WooCommerce cart integration with reactive state management using Nanostores.

### Installation

```bash
bun add @ferndev/woo
```

### Stores

The package exports reactive stores for cart state:

```typescript
import { $cart, $cartIsLoading, $shopConfig } from '@ferndev/woo';
```

| Store | Type | Description |
|-------|------|-------------|
| `$cart` | `Cart` | Current cart contents and totals |
| `$cartIsLoading` | `boolean` | Loading state for cart operations |
| `$shopConfig` | `ShopConfig` | Shop currency and price settings |

### Cart Type

```typescript
interface Cart {
    items: CartItem[];
    totals: {
        subtotal: number;
        total: number;
        tax: number;
        discount: number;
        shipping: number;
    };
    coupons: string[];
    item_count: number;
}

interface CartItem {
    key: string;
    product_id: number;
    variation_id?: number;
    quantity: number;
    name: string;
    price: number;
    total: number;
    image: string;
    variation?: Record<string, string>;
}
```

### initializeCart

Initialize cart state on app load.

```typescript
import { initializeCart } from '@ferndev/woo';

// Call on app mount
await initializeCart();
```

**Returns:** `Promise<ActionResult<{ cart: Cart; config: ShopConfig }>>`

**Usage in Astro:**
```astro
---
import CartIcon from '../components/CartIcon';
---

<CartIcon client:load />

<script>
import { initializeCart } from '@ferndev/woo';
initializeCart();
</script>
```

### addToCart

Add a product to the cart.

```typescript
import { addToCart } from '@ferndev/woo';

// Simple product
await addToCart({ productId: 123, quantity: 2 });

// Variable product
await addToCart({
    productId: 123,
    variationId: 456,
    variation: { size: 'large', color: 'blue' },
    quantity: 1
});
```

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| `productId` | `number` | Yes | Product ID |
| `quantity` | `number` | No | Quantity (default: 1) |
| `variationId` | `number` | No | Variation ID for variable products |
| `variation` | `object` | No | Variation attributes |
| `cartItemKey` | `string` | No | Existing cart item to modify |

### batchAddToCart

Add multiple products in one request.

```typescript
import { batchAddToCart } from '@ferndev/woo';

await batchAddToCart({
    items: [
        { productId: 123, quantity: 2 },
        { productId: 456, quantity: 1 },
        {
            productId: 789,
            variationId: 101,
            variation: { size: 'medium' }
        }
    ]
});
```

### updateCartItem

Update an existing cart item.

```typescript
import { updateCartItem } from '@ferndev/woo';

// Update quantity
await updateCartItem({
    cartItemKey: 'abc123',
    quantity: 5
});

// Change variation
await updateCartItem({
    cartItemKey: 'abc123',
    quantity: 2,
    variationId: 789,
    variation: { size: 'medium', color: 'red' }
});
```

### updateQuantity

Update item quantity (convenience method).

```typescript
import { updateQuantity } from '@ferndev/woo';

await updateQuantity('cart_item_key', 3);
```

### removeFromCart

Remove an item from the cart.

```typescript
import { removeFromCart } from '@ferndev/woo';

await removeFromCart('cart_item_key');
```

### clearCart

Remove all items from the cart.

```typescript
import { clearCart } from '@ferndev/woo';

await clearCart();
```

### getCart

Refresh cart state from server.

```typescript
import { getCart } from '@ferndev/woo';

await getCart();
```

### applyCoupon

Apply a coupon code.

```typescript
import { applyCoupon } from '@ferndev/woo';

const { data, error } = await applyCoupon('SAVE20');

if (error) {
    showError(error.message);
}
```

### removeCoupon

Remove an applied coupon.

```typescript
import { removeCoupon } from '@ferndev/woo';

await removeCoupon('SAVE20');
```

### formatPrice

Format a price according to shop configuration.

```typescript
import { formatPrice } from '@ferndev/woo';

formatPrice(1234.56);  // "$1,234.56"
formatPrice(-10.00);   // "-$10.00"
```

**Note:** Requires `initializeCart()` to be called first.

### Complete Cart Component Example

```tsx
import { useStore } from '@nanostores/solid';
import { createSignal } from 'solid-js';
import {
    $cart,
    $cartIsLoading,
    updateQuantity,
    removeFromCart,
    formatPrice
} from '@ferndev/woo';

export default function Cart() {
    const cart = useStore($cart);
    const isLoading = useStore($cartIsLoading);

    const handleQuantityChange = async (key: string, newQty: number) => {
        try {
            await updateQuantity(key, newQty);
        } catch (err) {
            console.error('Failed to update:', err);
        }
    };

    const handleRemove = async (key: string) => {
        try {
            await removeFromCart(key);
        } catch (err) {
            console.error('Failed to remove:', err);
        }
    };

    return (
        <div class="cart" classList={{ loading: isLoading() }}>
            {cart().items.length === 0 ? (
                <p>Your cart is empty</p>
            ) : (
                <>
                    <ul class="cart-items">
                        {cart().items.map((item) => (
                            <li class="cart-item">
                                <img src={item.image} alt={item.name} />
                                <div class="item-details">
                                    <h3>{item.name}</h3>
                                    {item.variation && (
                                        <p class="variation">
                                            {Object.entries(item.variation)
                                                .map(([k, v]) => `${k}: ${v}`)
                                                .join(', ')}
                                        </p>
                                    )}
                                    <p class="price">{formatPrice(item.price)}</p>
                                </div>
                                <div class="quantity">
                                    <button
                                        onClick={() => handleQuantityChange(item.key, item.quantity - 1)}
                                        disabled={isLoading() || item.quantity <= 1}
                                    >
                                        -
                                    </button>
                                    <span>{item.quantity}</span>
                                    <button
                                        onClick={() => handleQuantityChange(item.key, item.quantity + 1)}
                                        disabled={isLoading()}
                                    >
                                        +
                                    </button>
                                </div>
                                <p class="item-total">{formatPrice(item.total)}</p>
                                <button
                                    class="remove"
                                    onClick={() => handleRemove(item.key)}
                                    disabled={isLoading()}
                                >
                                    Remove
                                </button>
                            </li>
                        ))}
                    </ul>

                    <div class="cart-totals">
                        <div class="subtotal">
                            <span>Subtotal:</span>
                            <span>{formatPrice(cart().totals.subtotal)}</span>
                        </div>
                        {cart().totals.discount > 0 && (
                            <div class="discount">
                                <span>Discount:</span>
                                <span>-{formatPrice(cart().totals.discount)}</span>
                            </div>
                        )}
                        <div class="total">
                            <span>Total:</span>
                            <span>{formatPrice(cart().totals.total)}</span>
                        </div>
                    </div>

                    <a href="/checkout" class="checkout-button">
                        Proceed to Checkout
                    </a>
                </>
            )}
        </div>
    );
}
```

### Cart Icon with Count

```tsx
import { useStore } from '@nanostores/solid';
import { $cart } from '@ferndev/woo';

export default function CartIcon() {
    const cart = useStore($cart);
    const itemCount = () => cart().item_count || 0;

    return (
        <a href="/cart" class="cart-icon">
            <svg>/* cart icon */</svg>
            {itemCount() > 0 && (
                <span class="badge">{itemCount()}</span>
            )}
        </a>
    );
}
```

---

## PHP Controller for WooCommerce

Example controller implementing the required actions:

```php
<?php
namespace App\Controllers;

use App\Actions\WooCartActions;
use App\Services\Controllers\ViewController;
use Fern\Core\Services\Controller\Controller;
use Fern\Core\Services\HTTP\Reply;
use Fern\Core\Services\HTTP\Request;
use Fern\Core\Services\Views\Views;

class ShopController extends ViewController implements Controller {
    use WooCartActions;

    public static string $handle = 'product';

    public function handle(Request $request): Reply {
        return new Reply(200, Views::render('Product', [
            'product' => $this->getProductData(),
        ]));
    }
}
```

The `WooCartActions` trait provides:
- `getInitialState` - Cart and shop config
- `addToCart` - Add product
- `batchAddToCart` - Add multiple products
- `updateCartItem` - Update item
- `updateCartItemQuantity` - Update quantity
- `removeFromCart` - Remove item
- `clearCart` - Empty cart
- `getCartContents` - Fetch cart
- `applyCoupon` - Apply coupon
- `removeCoupon` - Remove coupon

---

## Best Practices

### 1. Initialize on Mount

```typescript
// In main layout or app entry
import { initializeCart } from '@ferndev/woo';

// Call once on app load
initializeCart();
```

### 2. Handle Loading States

```tsx
import { useStore } from '@nanostores/solid';
import { $cartIsLoading } from '@ferndev/woo';

const isLoading = useStore($cartIsLoading);

<button disabled={isLoading()}>
    {isLoading() ? 'Loading...' : 'Add to Cart'}
</button>
```

### 3. Handle Errors Gracefully

```typescript
try {
    await addToCart({ productId: 123 });
    showSuccess('Added to cart!');
} catch (error) {
    showError(error.message);
}
```

### 4. Use Type Safety

```typescript
interface ProductResponse {
    id: number;
    name: string;
    price: number;
}

const { data } = await callAction<ProductResponse>('getProduct', { id: 123 });
// data is typed as ProductResponse | undefined
```

### 5. Secure Sensitive Actions

```typescript
// Always use nonces for mutations
await callAction('deleteAccount', { confirm: true }, nonce);

// Read-only actions may not need nonces
await callAction('getProducts'); // Safe without nonce
```

---

## See Also

- [Controllers](./controllers.md) - PHP action methods
- [Views](./views.md) - Passing nonces to frontend
- [HTTP Layer](./http.md) - Action class details
