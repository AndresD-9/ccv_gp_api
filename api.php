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

// Ruta de login (autenticación)
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
            'phone' => $user->phone,
            ''
        ]
    ]);
});
// Ruta para obtener el rol del usuario
Route::get('/usuario/{id}/rol', function ($id) {
    $rol = DB::table('model_has_roles')
        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
        ->where('model_has_roles.model_id', $id)
        ->where('model_has_roles.model_type', 'App\\Models\\User')
        ->select('roles.name')
        ->first();

    return response()->json(['rol' => $rol->name ?? 'sin rol']);
});

// Ruta para obtener el grupo del usuario si es líder y sus grupos
Route::get('/usuario/{id}/grupo', function ($id) {
    $rol = DB::table('model_has_roles')
        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
        ->where('model_has_roles.model_id', $id)
        ->where('model_has_roles.model_type', 'App\\Models\\User')
        ->select('roles.name')
        ->first();

    if ($rol && $rol->name === 'lider') {
        $grupo = DB::table('lider_grupo')
            ->where('id_user', $id)
            ->select('id_grupo')
            ->first();

        return response()->json([
            'rol' => 'lider',
            'grupo' => $grupo->id_grupo ?? 'sin grupo asignado'
        ]);
    }

    return response()->json(['rol' => $rol->name ?? 'sin rol']);
});

// Ruta de logout (cerrar sesión)
Route::middleware('auth:sanctum')->post('/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();

    return response()->json(['status' => 'ok', 'message' => 'Sesión cerrada correctamente']);
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