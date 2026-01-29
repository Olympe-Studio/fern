# wordpress

## Events
sig: `class Events extends Hooks`
desc: WP actions wrapper
---
## on
sig: `static on(str|arr $event, callable $cb, int $pri=10, int $args=-1): void`
desc: Register event handler
params: event:str|arr:req:event name(s)|cb:callable:req|pri:int:opt:10|args:int:opt:auto
ex: `Events::on('init',fn()=>register_post_type('product',[]))`
---
## trigger
sig: `static trigger(str $name, mixed ...$args): void`
desc: Fire event
ex: `Events::trigger('my_plugin:order_created',$orderId)`
---
## renderToString
sig: `static renderToString(str $name, arr $args=[]): str`
desc: Trigger and capture output
ret: str:captured output
---
## removeHandlers
sig: `static removeHandlers(str|arr $event): void`
desc: Remove all handlers
---

## Filters
sig: `class Filters extends Hooks`
desc: WP filters wrapper
---
## on
sig: `static on(str|arr $filter, callable $cb, int $pri=10, int $args=-1): void`
desc: Register filter
ex: `Filters::on('the_content',fn($c)=>$c.'<p>Thanks!</p>')`
---
## apply
sig: `static apply(str $filter, mixed $val, mixed ...$args): mixed`
desc: Apply filter
ex: `$price=Filters::apply('format_price',$rawPrice,$currency)`
---
## removeHandlers
sig: `static removeHandlers(str|arr $filter): void`
---

# Fern Hooks

## Events
- `fern:core:before_boot` - before init
- `fern:core:after_boot` - after init
- `fern:core:config:after_boot` - after config
- `fern:core:reply:has_been_sent` - after response

## Filters
- `fern:core:config` - modify config
- `fern:core:ctx` - modify context
- `fern:core:views:ctx` - view context
- `fern:core:views:data` - view data
- `fern:core:views:result` - rendered HTML
- `fern:core:router:resolve_id` - modify page ID
- `fern:core:controller_resolve` - modify handle
- `fern:core:action:can_run` - gate actions
- `fern:core:reply:headers` - modify headers
- `fern:core:reply:will_be_send` - modify body
- `fern:core:file:disallowed_upload_extensions` - block exts
- `fern:core:file:allowed_mime_types` - allow mimes

# Common Patterns

## Post Types
```php
Events::on('init',fn()=>register_post_type('product',['public'=>true,'has_archive'=>true]));
```

## Scripts
```php
Events::on('wp_enqueue_scripts',fn()=>wp_enqueue_style('main',get_template_directory_uri().'/style.css'));
```

## Admin
```php
Events::on('admin_menu',fn()=>add_menu_page('Settings','Settings','manage_options','settings',[Page::class,'render']));
```
