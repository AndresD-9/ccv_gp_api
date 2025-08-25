<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Ruta de login
Route::post('/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required'
    ]);

    // Buscar usuario por email
    $user = User::where('email', $request->email)->first();

    // Validar usuario y contraseña
    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['error' => 'Credenciales inválidas'], 401);
    }

    // Validar que el usuario esté activo
    if ($user->activo !== 1) {
        return response()->json(['error' => 'Usuario no autorizado'], 403);
    }

    // Crear token
    $token = $user->createToken('app_token')->plainTextToken;

    return response()->json([
        'status' => 'ok',
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone
        ]
    ]);
});

// Ruta protegida de ejemplo
Route::middleware('auth:sanctum')->get('/productos', function () {
    return App\Models\Producto::all();
});
/* PRIMER TEST DE FUNCIONALIDAD
Route::get('/users', function () {
    $users = DB::table('users')->get();
    return response()->json($users);
});
*/