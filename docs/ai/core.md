# core

## Fern
sig: `class Fern extends Singleton`
desc: Main framework entry point, boots app
---
## defineConfig
sig: `static defineConfig(arr $config): void`
desc: Boot framework with config, triggers events
params: config:arr:req:root key req, rendering_engine, core, theme, mailer
throws: FernConfigurationExceptions:if App class missing
ex: `Fern::defineConfig(['root'=>__DIR__,'rendering_engine'=>new Astro()])`
notes: triggers fern:core:before_boot, fern:core:after_boot, calls App::boot()
---
## getVersion
sig: `static getVersion(): str`
ret: str:version string
ex: `Fern::getVersion() // "0.1.0"`
---
## isDev
sig: `static isDev(): bool`
ret: bool:true if WP_ENV=development
ex: `if(Fern::isDev()) { debug() }`
notes: result cached
---
## isNotDev
sig: `static isNotDev(): bool`
ret: bool:true if not development
---
## getRoot
sig: `static getRoot(): str`
ret: str:project root path from config
---
## passed
sig: `static passed(): bool`
ret: bool:true if router didn't handle req
---
## context
sig: `static context(): arr`
ret: arr:global app context
---

# Config
sig: `class Config extends Singleton`
desc: Config mgmt with dot notation, cached
---
## get
sig: `static get(str $key, mixed $def=null): mixed`
desc: Get config val with dot notation
params: key:str:req:dot notation key|def:mixed:opt:default val
ret: mixed:config val or def
ex: `Config::get('core.routes.disable.search',false)`
notes: results cached
---
## has
sig: `static has(str $key): bool`
desc: Check if config key exists
params: key:str:req:dot notation key
ret: bool
ex: `if(Config::has('mailer')) { send() }`
---
## all
sig: `static all(): arr`
ret: arr:full config
---
## toArray
sig: `static toArray(): arr`
ret: arr:alias for all()
---
## toJson
sig: `static toJson(): str`
ret: str:JSON config
---

# Context
sig: `class Context extends Singleton`
desc: Global app context for views
---
## set
sig: `static set(arr $ctx): void`
desc: Override app context
params: ctx:arr:req:context data
ex: `Context::set(['site_name'=>get_bloginfo('name')])`
---
## get
sig: `static get(): arr`
ret: arr:current context
---
## boot
sig: `static boot(): void`
desc: Init context, applies fern:core:ctx filter
---

# Singleton
sig: `abstract class Singleton`
desc: Base singleton pattern
---
## getInstance
sig: `static getInstance(arr ...$args): static`
desc: Get/create singleton instance
params: args:arr:opt:constructor args on first call
ret: static:singleton instance
ex: `$cache = Cache::getInstance()`
notes: protected ctor, no clone, no unserialize
