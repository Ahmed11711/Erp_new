/// Same Laravel `/api` base used by the Angular SPA (`front-end/src/env/env.ts`).
class ApiConfig {
  ApiConfig._();

  /// Local `php artisan serve` — switch to production when testing against live.
  static const String baseUrl = 'http://127.0.0.1:8000/api';
  static const String imgUrl = 'http://127.0.0.1:8000/images/';

  // static const String baseUrl =
  //     'https://erp.mag-opt.com/backend/public/api';
  // static const String imgUrl =
  //     'https://erp.mag-opt.com/backend/public/images/';

  static const Duration connectTimeout = Duration(seconds: 20);
  static const Duration receiveTimeout = Duration(seconds: 45);
}
