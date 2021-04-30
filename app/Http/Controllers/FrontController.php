<?php

namespace App\Http\Controllers;

use App\Mail\EmailChangeVerification;
use App\Mail\PasswordChangeVerification;
use App\Mail\VerifyUserEmail;
use App\Models\Cart;
use App\Models\DelieveryAddress;
use App\Models\Order;
use App\Models\OrderedProducts;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Models\Subcategory;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class FrontController extends Controller
{
    public function index()
    {
        if(Auth::user()->role_id == 1) {
            return view('backend.dashboard');

        } elseif(Auth::user()->role_id == 3) {
            $subcategories = Subcategory::latest()->get();
            $featuredproducts = Product::latest()->where('featured', 1)->get();
            $offerproducts = Product::latest()->where('discount', '>', 0)->take(6)->get();
            $filterproducts = Product::latest()->take(8)->get();
            $ratedproducts = Review::orderBy('rating', 'DESC')->with('product')->take(8)->get();
            return view('frontend.index', compact('subcategories', 'featuredproducts', 'offerproducts', 'filterproducts', 'ratedproducts'));
        }
    }
    public function shop()
    {
        $subcategories = Subcategory::latest()->get();
        $products = Product::latest()->get();
        $filterproducts = Product::latest()->take(6)->get();
        return view('frontend.shop', compact('subcategories', 'products', 'filterproducts'));
    }

    public function subcategory($slug)
    {
        $subcategory = Subcategory::where('slug', $slug)->first();
        $products = Product::latest()->where('subcategory_id', $subcategory->id)->get();
        $filterproducts = Product::latest()->take(6)->get();
        $subcategories = Subcategory::latest()->get();
        return view('frontend.subcategory', compact('subcategories', 'products', 'subcategory', 'filterproducts'));
    }

    public function contact()
    {
        $subcategories = Subcategory::latest()->get();
        return view('frontend.contact', compact('subcategories'));
    }

    public function products($slug)
    {
        $product = Product::where('slug', $slug)->first();
        $productimage = ProductImage::where('product_id', $product->id)->first();
        $productimages = ProductImage::where('product_id', $product->id)->get();
        $relatedproducts = Product::where('subcategory_id', $product->subcategory_id)->where('id', '!=', $product->id)->take(4)->get();
        $subcategories = Subcategory::latest()->get();
        return view('frontend.products', compact('subcategories', 'product', 'productimage', 'productimages', 'relatedproducts'));
    }

    public function checkout($id)
    {
        $cartproducts = Cart::where('user_id', $id)->get();
        $subcategories = Subcategory::latest()->get();
        return view('frontend.checkout', compact('subcategories', 'cartproducts'));
    }

    public static function verifyEmail($name, $email, $verification_code)
    {
        $mailData = [
            'name' => $name,
            'verification_code' => $verification_code,
        ];
        Mail::to($email)->send(new VerifyUserEmail($mailData));
    }

    public function cart()
    {
        $subcategories = Subcategory::latest()->get();
        $cartproducts = Cart::latest()->where('user_id', Auth::user()->id)->get();
        return view('frontend.cart', compact('subcategories', 'cartproducts'));
    }

    public function addtocart(Request $request, $id)
    {
        // dd($request['price']);
        $product = Product::findorFail($id);
        $cart = Cart::where('product_id', $id)->where('user_id', Auth::user()->id)->first();
        if($cart) {
            return redirect()->back()->with('failure', 'Product is already in cart. Go to cart.');
        } else{
            if ($product->quantity < $request['quantity']) {
                return redirect()->back()->with('failure', 'Quantity selected is more than of available.');
            } else{
                $cartproduct = Cart::create([
                    'user_id' => Auth::user()->id,
                    'product_id' => $id,
                    'quantity' => $request['quantity'],
                    'price' => $request['price']
                ]);

                $cartproduct->save();
                return redirect()->back()->with('success', 'Product is added in cart successfully.');
            }
        }
    }

    public function removefromcart($id)
    {
        $cart = Cart::where('user_id', Auth::user()->id)->where('id', $id)->first();
        $cart->delete();

        return redirect()->back()->with('success', 'Product is removed from cart successfully.');
    }

    public function updatequantity(Request $request, $id)
    {
        $cart = Cart::where('user_id', Auth::user()->id)->where('id', $id)->first();
        $product = Product::where('id', $cart->product_id)->first();
        if ($product->quantity < $request['quantity']) {
            return redirect()->back()->with('failure', 'Quantity more than available cannot be selected.');
        } else {
            $cart->update([
                'quantity' => $request['quantity'],
            ]);
            return redirect()->back()->with('success', 'Quantity is updated successfully.');
        }
    }

    public function wishlist()
    {
        $subcategories = Subcategory::latest()->get();
        $wishlistproducts = Wishlist::latest()->where('user_id', Auth::user()->id)->get();
        return view('frontend.wishlist', compact('subcategories', 'wishlistproducts'));
    }

    public function addtowishlist($id)
    {
        $wishlist = Wishlist::where('product_id', $id)->where('user_id', Auth::user()->id)->first();
        if($wishlist) {
            return redirect()->back()->with('failure', 'Product is already to wishlist. Go to wishlist.');
        } else{
            $wishlistproduct = Wishlist::create([
                'user_id' => Auth::user()->id,
                'product_id' => $id,
            ]);

            $wishlistproduct->save();
            return redirect()->back()->with('success', 'Product is added in wishlist successfully.');
        }
    }

    public function removefromwishlist($id)
    {
        $wishlist = Wishlist::where('user_id', Auth::user()->id)->where('product_id', $id)->first();
        $wishlist->delete();

        return redirect()->back()->with('success', 'Product is removed from wishlist successfully.');
    }

    public function addreview(Request $request){
        $data = $this->validate($request, [
            'star' => 'required',
            'username' => 'required',
            'product_id' => 'required',
        ]);

        $review = Review::create([
            'username' => $data['username'],
            'user_id' => Auth::user()->id,
            'product_id' => $data['product_id'],
            'rating' => $data['star'],
            'description' => $request['ratingdescription'],
        ]);

        $review->save();

        return redirect()->back()->with('success', 'Review added successfully');
    }

    public function updatereview(Request $request, $id)
    {
        $userreview = Review::findorfail($id);
        $data = $this->validate($request, [
            'star' => 'required',
        ]);
        $userreview->update([
            'rating' => $data['star'],
            'description' => $request['ratingdescription'],
        ]);
        $userreview->save();
        return redirect()->back()->with('success', 'Review updated successfully');
    }

    public function deleteuserreview($id)
      {
          $userreview = Review::findorfail($id);
          $userreview->delete();
          return redirect()->back()->with('success', 'Review Deleted Successfully');
      }

      public function myreviews()
      {
            $user_id = Auth::user()->id;
            $reviews = Review::where('user_id', $user_id)->latest()->simplePaginate(10);
            $subcategories = Subcategory::latest()->get();

            return view('frontend.myreviews', compact('reviews', 'subcategories'));

      }



      public function placeorder(Request $request)
      {
          $data = $this->validate($request, [
              'firstname' => 'required',
              'lastname' => 'required',
              'address' => 'required',
              'town' => 'required',
              'district' => 'required',
              'postcode' => 'required',
              'phone' => 'required',
              'email' => 'required|email'
            ]);

            $address = DelieveryAddress::where('user_id', Auth::user()->id)->first();
            if($address) {
                $delievery_address = DelieveryAddress::create([
                    'user_id' => Auth::user()->id,
                    'firstname' => $data['firstname'],
                    'lastname' => $data['lastname'],
                    'address' => $data['address'],
                    'town' => $data['town'],
                    'district' => $data['district'],
                    'postcode' => $data['postcode'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'is_default' => 0
                ]);
            }else {
                $delievery_address = DelieveryAddress::create([
                    'user_id' => Auth::user()->id,
                    'firstname' => $data['firstname'],
                    'lastname' => $data['lastname'],
                    'address' => $data['address'],
                    'town' => $data['town'],
                    'district' => $data['district'],
                    'postcode' => $data['postcode'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'is_default' => 1
                ]);
            }

            $delievery_address->save();

            $order = Order::create([
                'user_id' => Auth::user()->id,
                'delievery_address_id' => $delievery_address->id
            ]);

            $order->save();

            $cartproducts = Cart::where('user_id', Auth::user()->id)->get();

            foreach ($cartproducts as $cartproduct) {
                $product = Product::where('id', $cartproduct->product_id)->first();
                $quantity = $product->quantity - $cartproduct->quantity;

                $product->update([
                    'quantity' => $quantity
                ]);

                $ordered_products = OrderedProducts::create([
                    'order_id' => $order->id,
                    'product_id' => $cartproduct->product_id,
                    'quantity' => $cartproduct->quantity,
                    'price' => $cartproduct->price,
                    'status_id' => 1
                ]);

                $cartproduct->delete();

                $ordered_products->save();
            }
            return redirect()->route('index')->with('success', 'Thank you for ordering. We will call you soon');
      }

      public function myaccount()
    {
        $user = User::where('id', Auth::user()->id)->first();
        $title = $user->name;
        $delieveryaddress = DelieveryAddress::where('user_id', $user->id)->where('is_default', 1)->first();
        $subcategories = Subcategory::latest()->get();
        return view('frontend.myaccount', compact('user', 'delieveryaddress', 'subcategories'));
    }

    public function editaddress()
    {
        // $title = $user->name;
        $address = DelieveryAddress::where('user_id', Auth::user()->id)->where('is_default', 1)->first();
        $subcategories = Subcategory::latest()->get();

        return view('frontend.editaddress', compact('subcategories', 'address'));
    }

    public function updateaddress(Request $request, $id)
    {
        $data = $this->validate($request, [
            'firstname' => 'required',
            'lastname' => 'required',
            'phone' => 'required',
            'address' => 'required',
            'district' => 'required',
            'town' => 'required',
            'postcode' => 'required',
            'email'=>'required|email',
        ]);

        $address = DelieveryAddress::findorfail($id);

        $address->update([
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'address' => $data['address'],
            'town' => $data['town'],
            'district' => $data['district'],
            'postcode' => $data['postcode'],
            'phone' => $data['phone'],
            'email' => $data['email'],

        ]);
        return redirect()->route('myaccount')->with('success', 'Address information Updated Successfully');
    }

    public function myprofile()
    {
        $user = User::where('id', Auth::user()->id)->first();
        $subcategories = Subcategory::latest()->get();
        return view('frontend.myprofile.myprofile', compact( 'subcategories', 'user'));
    }

    public function editinfo()
    {

        $user = User::where('id', Auth::user()->id)->first();
        $subcategories = Subcategory::latest()->get();
        return view('frontend.myprofile.editinfo', compact( 'subcategories', 'user'));
    }

    public function sendEmailChange(Request $request)
    {
        $user = Auth::user();

        $data = $this->validate($request,[
            'name'=>'required',
            'email'=>'required|email',
        ]);

        Cookie::queue('emailcookie', $data['email'], 30);
        Cookie::queue('namecookie', $data['name'], 30);


        $mailData = [
            'name' => $data['name'],
            'verification_code' => $user->verification_code,
        ];
        Mail::to($data['email'])->send(new EmailChangeVerification($mailData));

        return redirect()->back()->with('success', 'Please verify from your newly given email');
    }

    public function useremailchange(){
        $verification_code = \Illuminate\Support\Facades\Request::get('code');
        $user = User::where('verification_code', $verification_code)->first();
        if( $user != null)
        {
            $username = Cookie::get('namecookie');
            $email = Cookie::get('emailcookie');

            $user->name = $username;
            $user->email = $email;
            $user->save();

            return redirect()->route('myprofile')->with('success', 'Your name and email has been changed as requested');
        }
        return redirect()->route('index')->with('error', 'Something is wrong.');
    }

    public function sendotpEmail() {
        $user = Auth::user();

        $email = $user->email;

        $otp = mt_rand(111111, 999999);

        Cookie::queue('otpcookie', $otp, 10);

        $mailData = [
            'otp' => $otp,
        ];

        Mail::to($email)->send(new PasswordChangeVerification($mailData));

        $subcategories = Subcategory::latest()->get();
        return view('frontend.myprofile.otpconfirmation', compact('subcategories'));

    }

    public function otpvalidation(Request $request)
    {

        $data = $this->validate($request, [
            'otpcode' => 'required|numeric',
        ]);

        $cookiedata = Cookie::get('otpcookie');

        if($data['otpcode'] == $cookiedata) {

            $subcategories = Subcategory::latest()->get();
            return view('frontend.myprofile.editpassword', compact( 'subcategories'));
        }
        else {
            return response()->json([
                'error_message' => 'Your otp code didnt match.'
            ], Response::HTTP_OK);
        }
    }

    public function updatePassword(Request $request)
    {
        $data = $this->validate($request,[
            'oldpassword' =>  'required',
            'newpassword' => 'required|min:8|confirmed|different:password',
        ]);

        $user = User::where('id', Auth::user()->id)->first();
        if(Hash::check($data['oldpassword'], $user->password))
        {
            if(!Hash::check($data['newpassword'], $user->password))
            {
                $newpassword = Hash::make($data['newpassword']);
                $user->update([
                    'password' => $newpassword,
                ]);
                $user->save();
                return redirect()->route('myprofile')->with('success', 'Password has been changed.');
            }
            else
            {
                return redirect()->back()
                        ->with('samepass', 'Old password cannot be new password.');
            }
        }
        else{
            return redirect()->back()
                    ->with('oldfailure', 'Your old password doesnot match our credentials.');
        }
    }
}
