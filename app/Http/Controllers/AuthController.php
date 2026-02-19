<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
     // POST /api/auth/login
    public function register(Request $request)
    {
        $request->validate([
            'name'=>'required|max:255',
            'username'=>'required|max:255',
            'email'=>'required|email|unique:users|max:255',
            'password'=>'required|string|max:255',
            'cats_number' => 'required|string|max:255',
            'department_code' => 'required|string|max:255',
        ]);

         $user=user::create([
            'name'=>$request->username,
            'username'=>$request->username,
            'email'=>$request->email,
            'password'=>Hash::make($request->password),
            'cats_number' => $request->cats_number,
            'department_code' => $request->department_code,
         ]);

        $token = $user->createToken('api_token')->plainTextToken;
        
        return response()->json([
            'message' => 'Register Successfully',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ], 201);

    }

    public function accessToken(Request $request)
    { 
        $request->validate([
            'username'=>'required',
            'password'=>'required',
        ]);

        $user=User::where('username', $request->username)->first();

        if(!$user||!Hash::check($request->password,$user->password)){
            throw ValidationException::withMessages([
                'message' => ['The provided credentials are incorrect']
            ]);
        }

        $token=$user->createToken('auth_token', ['get-details'])->plainTextToken;

        return response([
            'token'=>$token,    
            'token_type'=>'Bearer',
            'user' => $user
        ],201); 
    
    }


    public function logout(Request $request){
        $request->user()->tokens()->delete();

        return response([
            'message'=>'user logged out successfully'
        ],201);
    }
}
