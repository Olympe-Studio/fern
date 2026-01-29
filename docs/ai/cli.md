# cli

## fern:controller create
sig: `wp fern:controller create <name> <handle> [--subdir=<subdir>] [--create-page] [--light]`
desc: Generate controller boilerplate

### args
- name:str:req:controller class name (without 'Controller' suffix)
- handle:str:req:page ID|post type|taxonomy|archive_*|page

### opts
- --subdir:str:opt:subdirectory in App/Controllers/
- --create-page:flag:opt:create WP page when handle=page
- --light:flag:opt:minimal template without example actions

### examples
```bash
# Post type controller
wp fern:controller create Product product

# Page with auto-create
wp fern:controller create Contact page --create-page

# Subdirectory
wp fern:controller create Dashboard settings --subdir=Admin

# Minimal template
wp fern:controller create Simple post --light

# Taxonomy
wp fern:controller create ProductCategory product_cat

# Archive
wp fern:controller create ProductArchive archive_product
```

### output
Creates: `src/App/Controllers/[Subdir/]<Name>Controller.php`

### handle types
- `42` - page ID
- `product` - post type
- `product_cat` - taxonomy
- `archive_product` - post type archive
- `page` - creates new WP page (with --create-page)
- `_default` - fallback
- `_404` - not found

### templates
Full (default): includes example actions + RequireCapabilities
Light (--light): minimal handle() only

### notes
- Auto-removes 'Controller' suffix from name
- Creates subdirs if needed
- Errors if controller exists
