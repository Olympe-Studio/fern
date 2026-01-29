# frontend

## @ferndev/core

### callAction
sig: `callAction<T>(action:str,args?:obj|FormData,nonce?:str,opts?:{timeout?:num}):Promise<{data?:T,error?:{message:str,status?:num},status:'ok'|'error'}>`
desc: Make authenticated action req to PHP controller
params: action:str:req:method name|args:obj|FormData:opt|nonce:str:opt:CSRF token|opts.timeout:num:opt:30000ms
ex:
```ts
const {data,error}=await callAction<{success:bool}>('submitForm',{email:'a@b.com'},nonce)
if(error) console.error(error.message)
```

## @ferndev/woo

### Stores
- `$cart: Cart` - cart contents
- `$cartIsLoading: bool` - loading state
- `$shopConfig: ShopConfig` - currency/price settings

### Cart type
```ts
interface Cart {
  items:CartItem[]
  totals:{subtotal:num,total:num,tax:num,discount:num,shipping:num}
  coupons:str[]
  item_count:num
}
interface CartItem {
  key:str,product_id:num,variation_id?:num,quantity:num,name:str,price:num,total:num,image:str,variation?:Record<str,str>
}
```

### initializeCart
sig: `initializeCart():Promise`
desc: Init cart and shop config on app load
ex: `await initializeCart()`

### addToCart
sig: `addToCart({productId:num,quantity?:num,variationId?:num,variation?:obj,cartItemKey?:str}):Promise`
ex: `await addToCart({productId:123,quantity:2})`

### batchAddToCart
sig: `batchAddToCart({items:arr}):Promise`
ex: `await batchAddToCart({items:[{productId:1,quantity:2},{productId:2}]})`

### updateCartItem
sig: `updateCartItem({cartItemKey:str,quantity?:num,variationId?:num,variation?:obj}):Promise`

### updateQuantity
sig: `updateQuantity(cartItemKey:str,quantity:num):Promise`

### removeFromCart
sig: `removeFromCart(cartItemKey:str):Promise`

### clearCart
sig: `clearCart():Promise`

### getCart
sig: `getCart():Promise`
desc: Refresh cart from server

### applyCoupon
sig: `applyCoupon(code:str):Promise`

### removeCoupon
sig: `removeCoupon(code:str):Promise`

### formatPrice
sig: `formatPrice(price:num):str`
desc: Format price per shop config
throws: Error:if initializeCart not called
ex: `formatPrice(1234.56) // "$1,234.56"`

# Usage Pattern
```tsx
import {useStore} from '@nanostores/solid'
import {$cart,$cartIsLoading,addToCart,formatPrice} from '@ferndev/woo'

const cart=useStore($cart)
const loading=useStore($cartIsLoading)

<button onClick={()=>addToCart({productId:123})} disabled={loading()}>
  Add ({formatPrice(cart().totals.total)})
</button>
```
