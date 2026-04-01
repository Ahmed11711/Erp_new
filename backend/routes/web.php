<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/*
| صورة افتراضية لـ Meta WhatsApp template header (رابط https عام).
| إذا كان الملف الثابت في public/images مفقوداً أو تالفاً على السيرفر، يُعاد PNG صالح من هنا.
| (عند وجود الملف الثابت يخدمه Apache مباشرة ولا يُنفَّذ هذا المسار.)
*/
Route::get('/images/whatsapp-meta-default.png', function () {
    $path = public_path('images/whatsapp-meta-default.png');
    if (is_readable($path)) {
        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    if (function_exists('imagecreatetruecolor')) {
        $im = imagecreatetruecolor(512, 512);
        $bg = imagecolorallocate($im, 245, 245, 245);
        imagefill($im, 0, 0, $bg);
        ob_start();
        imagepng($im);
        imagedestroy($im);
        $binary = ob_get_clean();

        return response($binary, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    $minimal = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    return response($minimal, 200, [
        'Content-Type' => 'image/png',
        'Cache-Control' => 'public, max-age=86400',
    ]);
});

// Route::get('/', function () {
//     return view('welcome');
// });
