import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:geolocator/geolocator.dart';
import 'package:dio/dio.dart';
import '../../services/api_client.dart';
import '../../services/api_response.dart';
import '../../services/background_location_service.dart';
import '../../models/driver.dart';
import '../../models/json_value.dart';

class DriverProvider extends ChangeNotifier {
  final _storage = const FlutterSecureStorage();
  String? _token;
  Map<String, dynamic>? _driver;
  bool _isAuthenticated = false;
  bool _isLoading = false;
  String? _error;
  List<DriverDelivery> _deliveries = [];
  String _availability = 'offline';
  Timer? _pollTimer;
  Map<String, dynamic>? _earnings;

  String? get token => _token;
  Map<String, dynamic>? get driver => _driver;
  bool get isAuthenticated => _isAuthenticated;
  bool get isLoading => _isLoading;
  String? get error => _error;
  List<DriverDelivery> get deliveries => List.unmodifiable(_deliveries);
  String get availability => _availability;
  Map<String, dynamic>? get earnings => _earnings;

  List<DriverDelivery> get activeDeliveries =>
      _deliveries.where((d) => d.status != 'delivered').toList();

  Future<void> loadSavedToken() async {
    final savedToken = await _storage.read(key: 'driver_token');
    if (savedToken != null) {
      _token = savedToken;
      ApiClient().setAuthToken(savedToken);
      await fetchProfile();
      if (_isAuthenticated) await _registerFcmToken();
    }
  }

  Future<bool> login(String phone, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await ApiClient().post('/driver/login', data: {
        'phone': phone,
        'password': password,
      });
      final payload = ApiResponse.dataMap(response.data);

      if (ApiResponse.isSuccess(response.data) && payload != null) {
        final token = JsonValue.nullableString(payload['token']);
        final driver = JsonValue.object(payload['driver']);
        if (token == null || driver == null) {
          _error = 'The driver account response was incomplete. Please try again.';
          _isLoading = false;
          notifyListeners();
          return false;
        }
        _token = token;
        _driver = driver;
        _availability = JsonValue.string(_driver?['availability'], 'offline');
        _isAuthenticated = true;
        ApiClient().setAuthToken(_token!);
        await _storage.write(key: 'driver_token', value: _token);
        await _registerFcmToken();
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _error = ApiResponse.errorMessage(response.data, 'Login failed');
        _isLoading = false;
        notifyListeners();
        return false;
      }
    } catch (e) {
      _error = 'Login failed';
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> _registerFcmToken() async {
    try {
      final fcmToken = await _storage.read(key: 'fcm_token');
      if (fcmToken != null && fcmToken.isNotEmpty) {
        await ApiClient().post('/driver/me/fcm-token', data: {
          'fcm_token': fcmToken,
        });
      }
    } catch (_) {
      // FCM registration is best-effort; don't block login.
    }
  }

  Future<void> fetchProfile() async {
    try {
      final response = await ApiClient().get('/driver/me');
      final driver = ApiResponse.dataMap(response.data);
      if (ApiResponse.isSuccess(response.data) && driver != null) {
        _driver = driver;
        _availability = JsonValue.string(_driver?['availability'], 'offline');
        _isAuthenticated = true;
        _error = null;
        notifyListeners();
      } else {
        await logout();
      }
    } on DioException catch (error) {
      // A brief offline period must not erase a driver's saved session.
      if (error.response?.statusCode == 401) {
        await logout();
      } else {
        _error = 'Unable to refresh your driver profile. Check your connection.';
        notifyListeners();
      }
    } catch (_) {
      _error = 'Unable to refresh your driver profile. Please try again.';
      notifyListeners();
    }
  }

  Future<void> fetchDeliveries() async {
    try {
      final response = await ApiClient().get('/driver/deliveries');
      final data = ApiResponse.body(response.data);
      if (ApiResponse.isSuccess(response.data) && data?['data'] is List) {
        _deliveries = JsonValue.objectList(data!['data'])
            .map(DriverDelivery.fromJson)
            .toList();
        notifyListeners();
      }
    } catch (_) {}
  }

  Future<bool> updateDeliveryStatus(int assignmentId, String status) async {
    _isLoading = true;
    notifyListeners();

    try {
      final response = await ApiClient().patch(
        '/driver/deliveries/$assignmentId/status',
        data: {'status': status},
      );
      if (ApiResponse.isSuccess(response.data)) {
        await fetchDeliveries();
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _error = ApiResponse.errorMessage(response.data, 'Failed to update status');
        _isLoading = false;
        notifyListeners();
        return false;
      }
    } catch (_) {
      _error = 'Failed to update status';
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> verifyDeliveryOtp(int assignmentId, String otp) async {
    _isLoading = true;
    notifyListeners();

    try {
      final response = await ApiClient().post(
        '/driver/deliveries/$assignmentId/verify-otp',
        data: {'otp': otp},
      );
      if (ApiResponse.isSuccess(response.data)) {
        await fetchDeliveries();
        await fetchEarnings();
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _error = ApiResponse.errorMessage(response.data, 'OTP verification failed');
        _isLoading = false;
        notifyListeners();
        return false;
      }
    } catch (_) {
      _error = 'OTP verification failed';
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> fetchEarnings() async {
    try {
      final response = await ApiClient().get('/driver/earnings');
      final earnings = ApiResponse.dataMap(response.data);
      if (ApiResponse.isSuccess(response.data) && earnings != null) {
        _earnings = earnings;
        notifyListeners();
      }
    } catch (_) {}
  }

  Future<void> updateLocation(double lat, double lng) async {
    try {
      await ApiClient().post('/driver/location', data: {
        'latitude': lat,
        'longitude': lng,
      });
    } catch (_) {}
  }

  Future<void> shareCurrentLocation() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) return;
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) return;
      final position = await Geolocator.getCurrentPosition();
      await updateLocation(position.latitude, position.longitude);
    } catch (_) {
      // Location is optional: drivers can still accept and complete deliveries.
    }
  }

  Future<bool> setAvailability(String status) async {
    try {
      final response = await ApiClient().post('/driver/availability', data: {
        'availability': status,
      });
      if (ApiResponse.isSuccess(response.data)) {
        _availability = status;
        if (status == 'available') {
          BackgroundLocationService().start();
        } else {
          BackgroundLocationService().stop();
        }
        notifyListeners();
        return true;
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  void startPolling() {
    _pollTimer?.cancel();
    fetchDeliveries();
    fetchEarnings();
    // Start background location service (foreground service + GPS)
    if (_availability == 'available') {
      BackgroundLocationService().start();
    }
    _pollTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      fetchDeliveries();
    });
  }

  void stopPolling() {
    _pollTimer?.cancel();
    _pollTimer = null;
    BackgroundLocationService().stop();
  }

  Future<void> logout() async {
    stopPolling();
    BackgroundLocationService().stop();
    _token = null;
    _driver = null;
    _isAuthenticated = false;
    _deliveries = [];
    ApiClient().clearAuthToken();
    await _storage.delete(key: 'driver_token');
    notifyListeners();
  }

  @override
  void dispose() {
    stopPolling();
    super.dispose();
  }
}
