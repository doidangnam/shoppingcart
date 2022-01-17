<?php

namespace App\Http\Controllers\Shopping;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Product;
use App\Models\Slide;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{

    
    public function home()
    {
        $params = request()->all();
        $products = $this->getProductsList($params);
        $userFavoriteItems = null;
        
        if(isset(auth()->user()->id)) {
            $userFavoriteItems = Favorite::with('favoriteProducts')->where('user_id', auth()->user()->id)->get();
        }
        
        if (data_get($params, 'category')) {
            $categoryName = Category::where('slug', $params['category'])->first()->name;
        }
        else {
            $categoryName = 'Tất cả sản phẩm';
        }

        $highestPrice = 0;
        if (count($products) > 0) {
            $highestPrice = DB::select('SELECT price FROM products ORDER BY price DESC LIMIT 1')[0]->price;
        }
        return view('shopping.pages.home',[   
            'slides' => Slide::all(), 
            'categories' => Category::all(), 
            'categoryName' => $categoryName, 
            'userFavoriteItems' => $userFavoriteItems,
            'products' => $products,
            'highestPrice' => $highestPrice
        ]);
    }
    
    public function addToCart($id)
    {
        $product = Product::find($id);
        $product_image = Product::find($id)->productImages()->first();
        
        $cart = session()->get('cart');

        $cart[$id] = [
            
            "id" => $product->id,
            "name" => $product->name,
            "quantity" => 1,
            "price" => $product->price,
            "image"=> $product_image,
        ];

        session()->put('cart', $cart);
        session()->flash('success_add', "Product add to cart success");
        return redirect()->back();
    }

    public function update(Request $request)
    {
        if($request->id && $request->quantity){
            $cart = session()->get('cart');
            $cart[$request->id]["quantity"] = $request->quantity;
            session()->put('cart', $cart);

            session()->flash('success', 'Cart updated successfully');
        }
    }

    public function remove(Request $request)
    {
        if($request->id) {
            $cart = session()->get('cart');
            if(isset($cart[$request->id])) {
                unset($cart[$request->id]);
                session()->put('cart', $cart);
            }
            session()->flash('success', 'Product removed successfully');
        }
    }

    public function productsOnCategory($id)
    {
        try {
            $products = Product::where('category_id', $id)->get();
            return [
                'status' => true,
                'products' => $products
            ];
        } catch (Exception $e) {
            Log::error($e);
            return [
                'status' => false,
            ];
        }
    }

    protected function getProductsList($params) {
        if (data_get($params, 'category')) {
            $query = Product::with('category', 'productImages')
            ->whereHas ('category', function ($query) {
                $query->where ('slug', request()->category);
            });
        }
        else {
            $query = Product::with('productImages');
        }

        if (data_get($params, 'search')) {
            $query->where('name', 'LIKE', '%' .$params['search']. '%');
        }

        if (data_get($params, 'price-from') && data_get($params, 'price-to')) {
            $query->whereBetween('price', [$params['price-from'], $params['price-to']]);
        }   
        else if (data_get($params, 'price-from') && ! data_get($params, 'price-to')) {
            $query->where('price', '>=' , $params['price-from']);
        }
        else if (data_get($params, 'price-to')) {
            $query->where('price', '<=' , $params['price-to']);
        }

        return $query->orderBy('id', 'desc')->paginate(20);
    }

    public function blogList()
    {
        return view('shopping.pages.blog.blog-list');
    }

    public function blogSingle()
    {
        return view('shopping.pages.blog.blog-single');

    }

    public function products()
    {
        return view('shopping.pages.shop.products');
    }

    public function productDetails(Product $product)
    {
        return view('shopping.pages.shop.product-details', [
            'product' => $product,
            'category' => Category::where('id', $product->category_id)->firstOrFail(),
            'categories' => Category::all(), 
            'highestPrice' => DB::select('SELECT price FROM products ORDER BY price DESC LIMIT 1')[0]->price
        ]);
    }

    public function Checkout()
    {
        $provinces = DB::select('SELECT id, name FROM provinces');
        // dd(session('cart'));
        return view('shopping.pages.shop.checkout', compact('provinces'));
    }

    public function Cart()
    {
        return view('shopping.pages.shop.cart');

    }

    public function Login()
    {
        return view('shopping.pages.shop.login');

    }

    public function ContactUs()
    {
        return view('shopping.pages.contact_us.contact-us');
    }
}
