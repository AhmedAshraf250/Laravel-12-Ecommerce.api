<?php

use App\Http\Controllers\Api\PostController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

Route::name('api.')->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('auth:sanctum');


    // get all posts
    Route::get('/posts', function (Request $request) {
        // return posts Data
        $data = [
            [
                'id' => 1,
                'title' => 'Post 1',
                'content' => 'Content 1'
            ],
            [
                'id' => 2,
                'title' => 'Post 2',
                'content' => 'Content 2'
            ]
        ];
        return response()->json([
            'message' => 'All posts',
            'data' => $data
        ]);
    });
    // get single post
    Route::get('/posts/{id}', function (Request $request, $id) {
        return response()->json(['message' => 'Single post ' . $id]);
    });

    // create new post
    Route::post('/posts', function (Request $request) {
        return response()->json([
            'message' => 'Post created',
            'data' => $request->all()
        ]);
    });

    // put to update existing post
    Route::put('/posts/{id}', function (Request $request, $id) {
        return response()->json([
            'message' => 'Post ' . $id . ' updated',
            'data' => $request->all()
        ]);
    });

    // delete post
    Route::delete('/posts/{id}', function (Request $request, $id) {
        return response()->json(['message' => 'Post ' . $id . ' deleted']);
    });

    // simple get request
    Route::get('/hello', function (Request $request) {
        return response()->json(['message' => 'Hello World']);
    })->name('hello.world');




    // parameterized route
    // required parameters
    Route::get('posts/{id}/comments/{commentId}', function ($id, $commentId) {
        return response()->json([
            'message' => 'Post ' . $id . ' Comment ' . $commentId
        ]);
    })->name('posts.comments.show');

    // optional parameters
    Route::get('users/{id}/posts/{postId?}', function ($id, $postId = null) {
        if ($postId) {
            return response()->json([
                'message' => 'User ' . $id . ' Post ' . $postId
            ]);
        } else {
            return response()->json([
                'message' => 'User ' . $id . ' All Posts'
            ]);
        }
    })->name('users.posts.show');



    Route::get('test-header', function (Request $request) {
        return response()->json(['message' => 'Check the custom header in the response']);
    })->middleware('custom.header');




    Route::middleware('auth:sanctum')->get('/profile', function (Request $request) {
        return response()->json([
            'message' => 'User profile',
            'data' => $request->user()
        ]);
    })->name('profile');

    Route::post('/login', function (Request $request) {
        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'type' => 'Bearer',
        ]);
    })->name('login');

    Route::middleware('throttle:custom-api')->group(function () {
        Route::get('/limited', function (Request $request) {
            return response()->json([
                'message' => 'This route is rate limited to 5 requests per minute per IP address.',
            ]);
        });
    });

    Route::apiResource('posts', PostController::class);

    Route::get('user-profile', [ProfileController::class, 'index']);

    Route::post('posts/{id}/comments', [PostController::class, 'addcomment']);

    Route::post('users/{id}/roles', [RoleController::class, 'store']);
    Route::get('roles', [RoleController::class, 'index']);
});
