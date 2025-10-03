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

// Ruta para los grupos donde el usuario está asignado
Route::get('/usuario/{id}/grupo_asignado', function ($id) {
    $grupos = DB::table('grupo_has_user as gu')
        ->join('blogs as b', 'b.id', '=', 'gu.id_grupo')
        ->where('gu.id_user', $id)
        ->select('b.id', 'b.titulo', 'b.lugar', 'b.horario', 'b.contenido', 'b.imagen_nombre', 'b.lider')
        ->get();

    return response()->json([
        'usuario_id' => $id,
        'grupos' => $grupos,
    ]);
});

// Ruta de logout (cerrar sesión)
Route::middleware('auth:sanctum')->post('/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();

    return response()->json(['status' => 'ok', 'message' => 'Sesión cerrada correctamente']);
    // para cerrar todas las sesiones, usar: $request->user()->tokens()->delete();
});

// Ruta de registro de usuario
Route::post('/register', function (Request $request) {
    $request->validate([
        'name'     => 'required|string|max:255',
        'email'    => 'required|email|unique:users,email',
        'password' => 'required|min:6',
    ]);

    // Crear usuario
    $user = User::create([
        'name'     => $request->name,
        'email'    => $request->email,
        'password' => Hash::make($request->password),
        'activo'   => 0,// Por defecto inactivo
    ]);

    // Asignar rol si lo deseas (ejemplo: rol 'usuario')
    $rolUsuario = DB::table('roles')->where('name', 'usuario')->first();
    if ($rolUsuario) {
        DB::table('model_has_roles')->insert([
            'role_id'    => $rolUsuario->id,
            'model_type' => 'App\\Models\\User',
            'model_id'   => $user->id,
        ]);
    }

    return response()->json([
        'status' => 'ok',
        'message' => 'Usuario registrado correctamente',
        'user' => [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
        ]
    ]);
});

// Ruta protegida de ejemplo
Route::middleware('auth:sanctum')->get('/productos', function () {
    return App\Models\Producto::all();
});

// Ruta para obtener todos los grupos
Route::get('/grupos', function () {
    $grupos = DB::table('blogs')
        ->select('id', 'titulo', 'lugar', 'horario', 'contenido', 'imagen_nombre', 'lider')
        ->get();

    return response()->json([
        'total' => $grupos->count(),
        'grupos' => $grupos,
    ]);
});

Route::middleware('auth:sanctum')->post('/logout', function (Request $request) {
    // Elimina SOLO el token usado en esta sesión
    $request->user()->currentAccessToken()->delete();

    // O si quieres eliminar TODOS los tokens del usuario:
    // $request->user()->tokens()->delete();

    return response()->json(['message' => 'Sesión cerrada correctamente']);
});
/* PRIMER TEST DE FUNCIONALIDAD
Route::get('/users', function () {
    $users = DB::table('users')->get();
    return response()->json($users);
});

Route::get('/usuario/{id}/info_grupos', function ($id) {
    $rol = DB::table('model_has_roles')
        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
        ->where('model_has_roles.model_id', $id)
        ->where('model_has_roles.model_type', 'App\\Models\\User')
        ->select('roles.name')
        ->first();

    $grupoLiderado = null;
    if ($rol && $rol->name === 'lider') {
        $grupoLiderado = DB::table('lider_grupo')
            ->where('id_user', $id)
            ->value('id_grupo');
    }

    $gruposAsignados = DB::table('lider_grupo')
        ->where('id_user', $id)
        ->pluck('id_grupo');

    return response()->json([
        'usuario_id' => $id,
        'rol' => $rol->name ?? 'sin rol',
        'grupo_liderado' => $grupoLiderado ?? 'no aplica',
        'grupos_asignados' => $gruposAsignados,
    ]);
});

// Ruta para conocer el rol del usuario y sus grupos si es líder
Route::get('/usuario/{id}/lider_grupo', function ($id) {
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
*/