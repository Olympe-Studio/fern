# fern-framework

## Quick Reference

### Core
- `Fern::defineConfig(arr)` - boot framework
- `Config::get(str,def)` - get config with dot notation
- `Context::set(arr)` / `Context::get()` - global state

### HTTP
- `Request::getCurrent()` - get current req
- `$req->getAction()` - get Action obj
- `$req->getUrlParam(key,def)` - query params
- `new Reply(status,body)` - create response
- `$reply->send()` - send and exit

### Controllers
- Extend `ViewController implements Controller`
- Static `$handle` = page ID | post type | taxonomy | `archive_*` | `_default` | `_404`
- `handle(Request):Reply` - main handler
- Public methods = actions

### Attributes
- `#[Nonce('name')]` - validate nonce
- `#[RequireCapabilities(['cap'])]` - check caps
- `#[CacheReply(ttl:3600)]` - cache response

### Views
- `Views::render('Template',arr)` - render with data
- Context auto-merged into `$data['ctx']`

### Hooks
- `Events::on(name,cb)` - add action
- `Events::trigger(name,...args)` - fire action
- `Filters::on(name,cb)` - add filter
- `Filters::apply(name,val,...args)` - apply filter

### Cache
- `Cache::set(key,val,persist,ttl)` - store
- `Cache::get(key)` - retrieve
- `Cache::useMemo(fn,deps,ttl)` - memoize

### JSON
- `JSON::encode(data)` - to JSON
- `JSON::decode(str,assoc)` - from JSON
- `JSON::validate(str)` - check valid

### Frontend (@ferndev/core)
- `callAction(action,args,nonce)` - call PHP action

### Frontend (@ferndev/woo)
- `initializeCart()` - init on load
- `addToCart({productId,quantity})` - add item
- `updateQuantity(key,qty)` - update qty
- `removeFromCart(key)` - remove item
- `$cart` - reactive cart store
- `formatPrice(num)` - format with config

### CLI
- `wp fern:controller create <name> <handle>` - generate controller
  - `--subdir=X` - subdirectory
  - `--create-page` - create WP page
  - `--light` - minimal template

## Files
- [core.md](./core.md) - Fern,Config,Context,Singleton
- [http.md](./http.md) - Request,Reply,Action,File
- [controllers.md](./controllers.md) - Controller,Attributes
- [views.md](./views.md) - Views,RenderingEngine
- [wordpress.md](./wordpress.md) - Events,Filters,Hooks
- [utilities.md](./utilities.md) - Cache,JSON
- [frontend.md](./frontend.md) - @ferndev/core,@ferndev/woo
- [cli.md](./cli.md) - WP-CLI commands
