# controllers

## Controller
sig: `interface Controller`
desc: Controller contract
---
## handle
sig: `handle(Request $req): Reply`
desc: Process req, ret response
---
## getInstance
sig: `static getInstance(): static`
---

# ViewController
desc: Base controller extending Singleton
notes: Controllers need static $handle prop

## $handle types
- Page ID: `'42'` - specific page
- Post type: `'product'` - all posts of type
- Taxonomy: `'product_cat'` - term pages
- Archive: `'archive_product'` - post type archive
- Default: `'_default'` - fallback
- 404: `'_404'` - not found

ex:
```php
class ProductController extends ViewController implements Controller {
    public static string $handle = 'product';
    public function handle(Request $req): Reply {
        return new Reply(200, Views::render('Product', ['post'=>Timber::get_post()]));
    }
}
```

# AdminController
sig: `trait AdminController`
desc: WP admin page controller
---
## configure
sig: `abstract configure(): arr`
desc: Admin menu config
ret: arr:page_title,menu_title,capability,menu_slug,icon_url,position
ex:
```php
public function configure(): arr {
    return ['page_title'=>'Settings','menu_title'=>'Settings','capability'=>'manage_options','menu_slug'=>'my-settings'];
}
```

# Actions
desc: Public non-static methods are callable via callAction()
notes: Private/protected/static not exposed

# Attributes

## Nonce
sig: `#[Nonce(str $actionName)]`
desc: Validate WP nonce before exec
ex: `#[Nonce('contact_form')]`
notes: Returns 403 if invalid

## RequireCapabilities
sig: `#[RequireCapabilities(arr $caps)]`
desc: Check user capabilities
params: caps:arr:req:capability names
ex: `#[RequireCapabilities(['manage_options'])]`
notes: Returns 403 if missing caps

## CacheReply
sig: `#[CacheReply(int $ttl=3600, ?str $key=null, arr $varyBy=[])]`
desc: Cache action response
params: ttl:int:opt:seconds|key:str:opt:cache key|varyBy:arr:opt:params to vary by
ex: `#[CacheReply(ttl:1800,varyBy:['user_id'])]`

# Combining
```php
#[Nonce('save')]
#[RequireCapabilities(['edit_posts'])]
#[CacheReply(ttl:600)]
public function saveData(Request $req): Reply { }
```
