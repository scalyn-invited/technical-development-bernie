<?php
namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified'])->except('index');
    }

    public function index(): JsonResponse
    {
        $posts = Post::with('user')->latest()->get();
        return response()->json(['posts' => $posts]);
    }

    public function show(User $post): JsonResponse
    {
        return response()->json(['post' => $post]);
    }

    public function create(): View
    {
        return view('posts.create');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['title' => 'required', 'body' => 'required']);
        $post = Post::create($data);
        return response()->json($post, 201);
    }
}