# utilities

## Cache
sig: `class Cache extends Singleton`
desc: Two-level cache: in-memory + persistent
const: DEFAULT_EXPIRATION=14400 (4hr)
---
## get
sig: `static get(str $key): mixed`
ret: mixed|null:cached val or null if expired
---
## set
sig: `static set(str $key, mixed $val, bool $persist=false, int $exp=14400): void`
params: key:str:req|val:mixed:req|persist:bool:opt:save to db|exp:int:opt:ttl secs
ex: `Cache::set('data',$val,true,3600)`
---
## useMemo
sig: `static useMemo(callable $cb, arr $deps=[], int $exp=14400, bool $persist=false): callable`
desc: Memoize fn based on deps
params: cb:callable:req|deps:arr:opt:invalidation deps|exp:int:opt|persist:bool:opt
ret: callable:memoized fn
throws: InvalidArgumentException:if deps not serializable
ex:
```php
$getStats=Cache::useMemo(fn()=>$this->calcStats(),[$userId,$range],1800);
$stats=$getStats();
```
---
## flush
sig: `static flush(): void`
desc: Clear all caches
---
## save
sig: `static save(): void`
desc: Save persistent to db (auto on shutdown)
---

## JSON
sig: `final class JSON`
desc: Safe JSON utils
---
## encode
sig: `static encode(mixed $data, ?int $flags=null): str|false`
ret: str:JSON string
notes: def flags: UNESCAPED_UNICODE|UNESCAPED_SLASHES|THROW_ON_ERROR
ex: `JSON::encode(['key'=>'val'])`
---
## decode
sig: `static decode(str $json, bool $assoc=false, int $depth=512, int $flags=0): mixed`
params: json:str:req|assoc:bool:opt:ret arr|depth:int:opt|flags:int:opt
ret: mixed|null
ex: `JSON::decode($json,true)`
---
## decodeToArray
sig: `static decodeToArray(str $json, int $depth=512, int $flags=0): arr`
ret: arr
throws: JsonException:if empty/invalid/not arr
---
## validate
sig: `static validate(str $json, int $depth=512, int $flags=0): bool`
desc: Check valid JSON
ret: bool
ex: `if(JSON::validate($input)) { decode() }`
notes: Uses native json_validate() in PHP 8.3+
---
## pretty
sig: `static pretty(mixed $data): str|false`
desc: Encode with formatting
ret: str:formatted JSON
