<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Solicitud;
use App\Models\Blog;
use App\Models\Grupos;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordResetController; // Para recuperación

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

// --- RUTAS PÚBLICAS (No requieren token) ---

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
    if ($user->activo !== 1) { // Descomenta si usas 'activo'
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
            'phone' => $user->phone ?? null,
        ]
    ]);
});

// Rutas para recuperación de contraseña (Públicas)
// 1. Solicitar el token (código de 6 dígitos)
Route::post('password/email', [PasswordResetController::class, 'sendResetToken']);
// 2. Verificar que el token sea válido
Route::post('password/verify-token', [PasswordResetController::class, 'verifyToken']);
// 3. Actualizar la contraseña
Route::post('password/reset', [PasswordResetController::class, 'resetPassword']);

// Ruta de registro de usuario
Route::post('/register', function (Request $request) {
    $request->validate([
        'name'     => 'required|string|max:255',
        'email'    => 'required|email|unique:users,email',
        'password' => 'required|min:6',
    ]);
    $user = User::create([
        'name'     => $request->name,
        'email'    => $request->email,
        'password' => Hash::make($request->password),
        // 'activo'   => 0, // Si usas 'activo'
    ]);
    // Asignar rol 'usuario' (Asegúrate que exista el rol 'usuario')
    // $user->assignRole('usuario'); // Usando Spatie si está configurado
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
        'user' => $user->only(['id', 'name', 'email']) // Devuelve solo datos seguros
    ], 201); // Código 201 para recurso creado
});

// --- RUTAS PROTEGIDAS (Requieren token vía 'auth:sanctum') ---
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Ruta para obtener el rol del usuario
    Route::get('/usuario/{id}/rol', function ($id) {
        // Importante: Verifica que el usuario autenticado pueda ver este rol
        if (auth()->id() != $id /* && !auth()->user()->hasRole('admin') */) { // Ejemplo de verificación
             return response()->json(['error' => 'No autorizado'], 403);
        }
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
        // Importante: Verifica permisos
         if (auth()->id() != $id /* && !auth()->user()->hasRole('admin') */) { // Ejemplo
             return response()->json(['error' => 'No autorizado'], 403);
         }
        $grupos = DB::table('grupo_has_user as gu')
            ->join('blogs as b', 'b.id', '=', 'gu.id_grupo') // Asumiendo que 'blogs' es la tabla de grupos
            ->where('gu.id_user', $id)
            ->select('b.id', 'b.titulo', 'b.lugar', 'b.horario', 'b.contenido', 'b.imagen_nombre', 'b.lider')
            ->get();
        return response()->json([
            'usuario_id' => $id,
            'grupos' => $grupos,
        ]);
    });

    // Ruta de logout (cerrar sesión)
    Route::post('/logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete(); // Borra solo el token actual
        return response()->json(['status' => 'ok', 'message' => 'Sesión cerrada correctamente']);
    });

    // Ruta para obtener todos los grupos
    Route::get('/grupos', function () {
        $grupos = DB::table('blogs') // Asumiendo 'blogs' es la tabla de grupos
            ->select('id', 'titulo', 'lugar', 'horario', 'contenido', 'imagen_nombre', 'lider')
            ->get();
        return response()->json([
            'total' => $grupos->count(),
            'grupos' => $grupos,
        ]);
    });

    // Ruta para solicitar unirse a un grupo
    Route::post('/solicitar-grupo', function (Request $request) {
        $validated = $request->validate([
            'id_grupo' => 'required|integer|exists:blogs,id', // Asumiendo 'blogs'
        ]);
        $user = $request->user(); // Usuario autenticado
        $yaSolicito = Solicitud::where('id_user', $user->id)
            ->where('id_grupo', $validated['id_grupo'])
            ->exists();
        if ($yaSolicito) {
            return response()->json(['error' => 'Ya enviaste una solicitud para este grupo'], 409); // Código 409 Conflict
        }
        $grupo = Blog::find($validated['id_grupo']);
        if (!$grupo) { // Añade verificación por si acaso
             return response()->json(['error' => 'Grupo no encontrado'], 404);
        }
        $solicitud = Solicitud::create([
            'id_user'    => $user->id,
            'id_grupo'   => $grupo->id,
            'name_user'  => $user->name,
            'name_grupo' => $grupo->titulo,
            'email'      => $user->email,
            'estado'     => 'pendiente',
        ]);
        return response()->json([
            'message' => 'Solicitud enviada correctamente',
            'solicitud' => $solicitud,
        ], 201);
    });

    // --- Debido al hosting no es posible conectarse con las notificaciones ---
    // Guardar el token FCM de un dispositivo
    Route::post('/save-fcm-token', [DeviceTokenController::class, 'saveToken']);

    // Enviar una notificación (ej. un mensaje)
    Route::post('/send-notification', [NotificationController::class, 'sendNotification']);

    // Ruta para actualizar el perfil del usuario
    Route::put('/perfil', function (Request $request) {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
        ]);
        
        $user = $request->user(); // Usuario autenticado
        $user->update([
            'name' => $validated['nombre'],
        ]);
        
        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'user' => $user,
        ], 200);
    });

    // Ruta para cambiar la contraseña del usuario
    Route::post('/cambiar-contrasena', function (Request $request) {
        $validated = $request->validate([
            'contrasena_actual' => 'required|string',
            'contrasena_nueva' => 'required|string|min:8',
        ]);
        
        $user = $request->user();
        
        // Verifica que la contraseña actual sea correcta
        if (!Hash::check($validated['contrasena_actual'], $user->password)) {
            return response()->json([
                'error' => 'La contraseña actual es incorrecta',
            ], 401);
        }
        
        // Actualiza la contraseña
        $user->update([
            'password' => Hash::make($validated['contrasena_nueva']),
        ]);
        
        return response()->json([
            'message' => 'Contraseña actualizada correctamente',
        ], 200);
    });

}); // <-- FIN DEL GRUPO PROTEGIDO


// --- BORRA LAS RUTAS DE TEST COMENTADAS SI YA NO LAS NECESITAS ---
/*
Route::get('/users', function () {
    $users = DB::table('users')->get();
    return response()->json($users);
});
// ... (resto de rutas comentadas) ...
*/



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

