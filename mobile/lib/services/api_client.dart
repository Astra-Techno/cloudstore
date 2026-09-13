import 'dart:async';
import 'dart:developer' as developer;

import 'package:dio/dio.dart';
import '../config/app_config.dart';

/// Retries requests on network/timeout errors (not on HTTP error responses).
class _RetryInterceptor extends Interceptor {
  final Dio _dio;
  static const _maxRetries = 2;
  static const _retryDelay = Duration(seconds: 1);
  static const _retryKey = 'x-retry-count';

  _RetryInterceptor(this._dio);

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    if (!_shouldRetry(err)) {
      return handler.next(err);
    }

    final retryCount = err.requestOptions.extra[_retryKey] as int? ?? 0;
    if (retryCount >= _maxRetries) {
      return handler.next(err);
    }

    await Future.delayed(_retryDelay);

    final options = err.requestOptions;
    options.extra[_retryKey] = retryCount + 1;

    try {
      final response = await _dio.fetch(options);
      handler.resolve(response);
    } on DioException catch (e) {
      handler.next(e);
    }
  }

  bool _shouldRetry(DioException err) {
    return err.type == DioExceptionType.connectionTimeout ||
        err.type == DioExceptionType.sendTimeout ||
        err.type == DioExceptionType.receiveTimeout ||
        err.type == DioExceptionType.connectionError;
  }
}

class ApiClient {
  late final Dio _dio;
  String? _authToken;
  String? _appToken;
  String? _marketplaceStore;

  static final ApiClient _instance = ApiClient._internal();
  factory ApiClient() => _instance;

  ApiClient._internal() {
    _dio = Dio(BaseOptions(
      baseUrl: AppConfig.apiBaseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 15),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    ));

    // Retry interceptor for network failures (2 retries, 1s delay)
    _dio.interceptors.add(_RetryInterceptor(_dio));

    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) {
        if (_appToken != null) {
          options.headers['X-App-Token'] = _appToken;
        }
        if (_marketplaceStore != null) {
          options.headers['X-Marketplace-Store'] = _marketplaceStore;
        }
        if (_authToken != null) {
          options.headers['Authorization'] = 'Bearer $_authToken';
        }
        // Deliberately excludes headers and request data: both can contain
        // credentials, OTPs, customer details, or payment information.
        developer.log(
          '${options.method} ${options.uri.path}',
          name: 'CloudMarket.Api',
        );
        handler.next(options);
      },
      onResponse: (response, handler) {
        developer.log(
          '${response.requestOptions.method} ${response.requestOptions.uri.path} -> ${response.statusCode}',
          name: 'CloudMarket.Api',
        );
        handler.next(response);
      },
      onError: (error, handler) {
        developer.log(
          '${error.requestOptions.method} ${error.requestOptions.uri.path} -> ${error.response?.statusCode ?? error.type.name}',
          name: 'CloudMarket.Api',
        );
        handler.next(error);
      },
    ));
  }

  void setAppToken(String token) {
    _appToken = token;
    _marketplaceStore = null;
  }

  void setMarketplaceStore(String slug) {
    _marketplaceStore = slug;
    _appToken = null;
  }

  void setAuthToken(String token) {
    _authToken = token;
  }

  void clearAuthToken() {
    _authToken = null;
  }

  Future<Response> get(String path, {Map<String, dynamic>? queryParameters}) {
    return _dio.get(path, queryParameters: queryParameters);
  }

  Future<Response> post(String path, {dynamic data}) {
    return _dio.post(path, data: data);
  }

  Future<Response> put(String path, {dynamic data}) {
    return _dio.post(path, data: data, options: Options(headers: {'X-HTTP-Method-Override': 'PUT'}));
  }

  Future<Response> patch(String path, {dynamic data}) {
    return _dio.post(path, data: data, options: Options(headers: {'X-HTTP-Method-Override': 'PATCH'}));
  }

  Future<Response> delete(String path) {
    return _dio.post(path, options: Options(headers: {'X-HTTP-Method-Override': 'DELETE'}));
  }
}
