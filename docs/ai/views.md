# views

## Views
sig: `class Views`
desc: Template rendering facade
---
## render
sig: `static render(str $template, arr $data=[], bool $doingBlock=false): str`
desc: Render template with data
params: template:str:req:template name|data:arr:opt:view data|doingBlock:bool:opt:block context
ret: str:rendered HTML
throws: InvalidArgumentException:if data not arr
ex: `Views::render('Product',['title'=>$post->title()])`
notes: Auto-merges Context, applies filters

# Filters

## fern:core:views:ctx
desc: Inject global context
ex:
```php
Filters::on('fern:core:views:ctx',fn($ctx)=>[...$ctx,'menu'=>wp_get_nav_menu_items('primary')]);
```

## fern:core:views:data
desc: Modify view data
ex:
```php
Filters::on('fern:core:views:data',fn($data)=>[...$data,'time'=>time()]);
```

## fern:core:views:result
desc: Modify rendered HTML
ex:
```php
Filters::on('fern:core:views:result',fn($html)=>minify($html));
```

# RenderingEngine
sig: `interface RenderingEngine`
desc: Engine contract
---
## render
sig: `render(str $template, arr $data=[]): str`
---
## renderBlock
sig: `renderBlock(str $block, arr $data=[]): str`
---
## boot
sig: `boot(): void`

# Template Resolution
- `'HomePage'` -> `resources/src/pages/HomePage.astro`
- `'Admin/Settings'` -> `resources/src/pages/Admin/Settings.astro`

# Context
- Views auto-merge Context::get() into $data['ctx']
- Access via `Astro.props.ctx`

# Nonces in Views
```php
Views::render('Page',['nonces'=>['form'=>wp_create_nonce('form')]]);
```
```astro
const { nonces } = Astro.props;
// nonces.form available
```
