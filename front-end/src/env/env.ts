export const environment = {
  production: true,
  /**
   * Laravel API base URL including `/api` suffix.
   * Examples:
   * - `php artisan serve` → http://127.0.0.1:8000/api
   * - XAMPP → http://localhost/Erp_new/backend/public/api
   */
  Url: 'https://erp.mag-opt.com/backend/public/api', imgUrl: 'https://erp.mag-opt.com/backend/public/images/',
  // Url: 'https://chat.mag-opt.com/backend/public/api', imgUrl: 'https://chat.mag-opt.com/backend/public/images/',
  // Url: 'https://test.mag-opt.com/backend/public/api', imgUrl: 'https://test.mag-opt.com/backend/public/images/',
  // Url: 'http://127.0.0.1:8000/api', imgUrl: 'http://127.0.0.1:8000/images/',
  // Url: 'http://magaliserp.test/api',imgUrl: 'http://magaliserp.test/images/',
  pusher: {
    key: 'laravelWebSocketKey',
    cluster: 'mt1'
  }
};
