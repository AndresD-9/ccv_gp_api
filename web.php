<?php

use Illuminate\Support\Facades\Route;
//agregamos los siguientes controladores
use App\Http\Controllers\HomeController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\GruposController;
use App\Http\Controllers\InscritosController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\DifusionController;
use App\Http\Controllers\ForoController;
use App\Http\Controllers\CategoriaForoController;
use App\Http\Controllers\TemaForoController;
use App\Http\Controllers\PublicacionForoController;
use App\Http\Controllers\ProfileController;

/*
Route::get('/', function () {
    return view('welcome');
}); */
Route::get('/', [GruposController::class, 'index'])->name('grupos.index');
//ruta publica 
Route::get('/grupos', [GruposController::class, 'index'])->name('grupos.index');

Route::get('/ingreso', [GruposController::class, 'ingreso'])->name('grupos.ingreso');
Route::post('/guardar', [GruposController::class, 'guardar'])->name('grupos.guardar');
Route::get('/inscritos', 'App\Http\Controllers\InscritosController@index');
Route::get('/solicitud', 'App\Http\Controllers\InscritosController@solicitud');

Route::resource('foros', ForoController::class);
Route::resource('categorias', CategoriaForoController::class);
Route::resource('temas', TemaForoController::class);
Route::resource('publicaciones', PublicacionForoController::class);
// Route::prefix('foros/{foroId}/temas')->name('temas.')->group(function () {
//     Route::get('/', [TemaForoController::class, 'index'])->name('index');
//     Route::get('/create', [TemaForoController::class, 'create'])->name('create');    
//     Route::post('/', [TemaForoController::class, 'store'])->name('store');
// });

Route::middleware(['auth'])->group(function () {
    Route::get('/temas/create/{foro_id}', [TemaForoController::class, 'create'])->name('temas.create');
    Route::post('/temas/store/{foro_id}', [TemaForoController::class, 'store'])->name('temas.store');
});
Route::delete('/foros/{foro}', [ForoController::class, 'destroy'])->name('foros.destroy')->middleware('can:eliminar-foro,foro');

Route::delete('/temas/{tema}', [TemaForoController::class, 'destroy'])
    ->name('temas.destroy')
    ->middleware(['auth']);

Route::post('/temas/{tema}/publicaciones', [PublicacionForoController::class, 'store'])
    ->name('publicaciones.store')
    ->middleware('auth');
    
// Ruta para mostrar el formulario de respuesta
Route::get('/publicaciones/{id}/responder', [PublicacionForoController::class, 'responder'])->name('publicaciones.responder');

Route::middleware(['auth'])->group(function () {
    Route::resource('publicaciones', PublicacionForoController::class)->except(['index', 'show']);
});

//Route::resource('publicaciones', PublicacionController::class);
Route::resource('publicaciones', PublicacionForoController::class)->except(['index', 'show']);

Route::get('tema/{publicacion}/edit', [PublicacionForoController::class, 'edit'])->name('publicaciones.edit');
Route::put('tema/{publicacion}', [PublicacionForoController::class, 'update'])->name('publicaciones.update');
Route::delete('tema/{publicacion}', [PublicacionForoController::class, 'destroy'])->name('publicaciones.destroy');

Route::post('/inscritos/accept/{id_user}/{id_grupo}/{id}', [InscritosController::class, 'accept'])->name('inscritos.accept');
Route::post('/inscritos/ingresar', [InscritosController::class, 'ingresar'])->name('inscritos.ingresar');
Route::delete('/inscritos/sacar/{id}/{id_user}', [InscritosController::class, 'sacar'])->name('inscritos.sacar');
Route::delete('/inscritos/destroy/{id}', [InscritosController::class, 'destroy'])->name('inscritos.destroy');

 Auth::routes();

 Route::middleware(['auth'])->group(function () {
    Route::put('/profile/update', [ProfileController::class, 'update'])->name('profile.update');
});

 Route::match(['get', 'post'], '/home', [HomeController::class, 'index'])->name('home');
Route::get('usuarios/asignarlider',[ UsuarioController::class,'asignarlider'])->name('usuarios.asignarlider');
Route::post('/usuarios/agregarlider', [UsuarioController::class,'agregarlider'])->name('usuarios.agregarlider');
Route::post('usuarios/asignar',[ UsuarioController::class,'asignar'])->name('usuarios.asignar');
Route::post('usuarios/borrar',[ UsuarioController::class,'borrar'])->name('usuarios.borrar');
Route::post('/change-password', [UsuarioController::class, 'changePassword'])->name('password.change');

Route::get('/asistencia/{id_grupo}', [AsistenciaController::class, 'mostrarAsistencia'])->name('asistencia.ingresar');
Route::post('/asistencia/{id_grupo}', [AsistenciaController::class, 'guardarAsistencia'])->name('asistencia.guardar');
Route::get('/asistencia/historico/{id_grupo}', [AsistenciaController::class, 'historicoAsistencia'])->name('asistencia.historico');
Route::get('/asistencia/detalle/{id_asistencia}', [AsistenciaController::class, 'detalleAsistencia'])->name('asistencia.detalle');
Route::get('/difusion/{id_grupo}', [DifusionController::class, 'index'])->name('difusion.index');
Route::post('/difusion/{id_grupo}/enviar', [DifusionController::class, 'enviar'])->name('difusion.enviar');

//rutas protegidas para los controladores
Route::group(['middleware' => ['auth']], function() {
    Route::resource('roles', RolController::class);
    Route::resource('usuarios', UsuarioController::class);
    Route::resource('blogs', BlogController::class);
    Route::resource('foros', ForoController::class);
});

// Route::resource('foros', ForoController::class)->middleware('auth');