# http

## Request
sig: `class Request extends Singleton`
desc: HTTP req wrapper with WP context
---
## getCurrent
sig: `static getCurrent(): Request`
ret: Request:current req instance
---
## getId
sig: `getId(): int`
ret: int:WP obj ID (post/page/term)
---
## getBody
sig: `getBody(): arr|str`
ret: arr|str:parsed req body
---
## getBodyParam
sig: `getBodyParam(str $key): mixed`
params: key:str:req:param name
ret: mixed|null
---
## getUrlParam
sig: `getUrlParam(str $key, mixed $def=null): mixed`
params: key:str:req:param name|def:mixed:opt:default
ret: mixed
ex: `$page=$req->getUrlParam('page',1)`
---
## getUrlParams
sig: `getUrlParams(): arr`
ret: arr:all query params
---
## hasUrlParam
sig: `hasUrlParam(str $name): bool`
---
## getHeader
sig: `getHeader(str $key): str|null`
---
## getHeaders
sig: `getHeaders(): arr`
---
## hasHeader
sig: `hasHeader(str $header): bool`
---
## getCookie
sig: `getCookie(str $name): str|null`
---
## hasCookie
sig: `hasCookie(str $name): bool`
---
## getAction
sig: `getAction(): Action`
ret: Action:current action obj
---
## getMethod
sig: `getMethod(): str`
ret: str:GET|POST|PUT|DELETE|PATCH
---
## isGet
sig: `isGet(): bool`
---
## isPost
sig: `isPost(): bool`
---
## isAction
sig: `isAction(): bool`
desc: Check if X-Fern-Action header set
---
## isAjax
sig: `isAjax(): bool`
---
## isREST
sig: `isREST(): bool`
---
## isCRON
sig: `isCRON(): bool`
---
## isCLI
sig: `isCLI(): bool`
---
## is404
sig: `is404(): bool`
---
## isHome
sig: `isHome(): bool`
---
## isPage
sig: `isPage(): bool`
---
## isArchive
sig: `isArchive(): bool`
---
## isTerm
sig: `isTerm(): bool`
---
## getPostType
sig: `getPostType(): str|null`
---
## getTaxonomy
sig: `getTaxonomy(): str|null`
---
## set404
sig: `set404(): never`
desc: Force 404 and redirect
---

# Reply
sig: `class Reply`
desc: HTTP response builder
---
## __construct
sig: `__construct(int $status=200, mixed $body='', str $type=null, arr $headers=[])`
params: status:int:opt:200|body:mixed:opt:response body|type:str:opt:auto-detect|headers:arr:opt
ex: `new Reply(200,['success'=>true])`
notes: arr body auto-sets application/json
---
## send
sig: `send(mixed $data=null): never`
desc: Send response and exit
ex: `$reply->send()`
---
## code
sig: `code(int $code): self`
desc: Set status code
---
## setBody
sig: `setBody(mixed $body): self`
---
## getBody
sig: `getBody(): mixed`
---
## contentType
sig: `contentType(str $type): self`
---
## setHeader
sig: `setHeader(str $key, mixed $val): self`
---
## getHeader
sig: `getHeader(str $key): mixed`
---
## redirect
sig: `redirect(str $to): never`
desc: Redirect and exit
ex: `$reply->redirect('/login')`
---
## hijack
sig: `hijack(): self`
desc: Prevent auto-send for streaming
---
## toArray
sig: `toArray(): arr`
desc: Serialize for caching
---
## fromArray
sig: `static fromArray(arr $data): Reply`
desc: Restore from cache
---

# Action
sig: `class Action`
desc: Frontend action req handler
---
## getCurrent
sig: `static getCurrent(): Action`
---
## getName
sig: `getName(): str|null`
ret: str:action method name
---
## isBadRequest
sig: `isBadRequest(): bool`
---
## get
sig: `get(str $key, mixed $def=null): mixed`
desc: Get action arg
ex: `$action->get('email')`
---
## getRawArgs
sig: `getRawArgs(): arr`
---
## has
sig: `has(str $key): bool`
---
## add
sig: `add(str $key, mixed $val): self`
---
## merge
sig: `merge(arr $data): self`
---

# File
sig: `class File`
desc: Upload handler with security
---
## getAllFromCurrentRequest
sig: `static getAllFromCurrentRequest(): arr<File>`
ret: arr:all uploaded files
ex: `$files=File::getAllFromCurrentRequest()`
---
## upload
sig: `upload(?str $path=null): void`
desc: Upload to WP uploads dir
params: path:str:opt:subdirectory
throws: FileHandlingError:on validation/upload fail
ex: `$file->upload('avatars/2024')`
---
## isFileExtensionAllowed
sig: `isFileExtensionAllowed(): bool`
---
## delete
sig: `delete(): void`
---
## getUrl
sig: `getUrl(): str|null`
ret: str:URL after upload
---
## getName
sig: `getName(): str`
ret: str:original filename
---
## getType
sig: `getType(): str`
ret: str:MIME type
---
## getSize
sig: `getSize(): int`
ret: int:bytes
