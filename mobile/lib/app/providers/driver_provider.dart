import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:geolocator/geolocator.dart';
import '../../services/api_client.dart';
import '../../models/driver.dart';

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

  String? get token => _token;
  Map<String, dynamic>? get driver => _driver;
  bool get isAuthenticated => _isAuthenticated;
  bool get isLoading => _isLoading;
  String? get error => _error;
  List<DriverDelivery> get deliveries => List.unmodifiable(_deliveries);
  String get availability => _availability;

  List<DriverDelivery> get activeDeliveries =>
      _deliveries.where((d) => d.status != 'delivered').toList();

  Future<void> loadSavedToken() async {
    final savedToken = await _storage.read(key: 'driver_token');
    if (savedToken != null) {
      _token = savedToken;
      ApiClient().setAuthToken(savedToken);
      await fetchProfile();
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
      final data = response.data;

      if (data['success'] == true && data['data'] != null) {
        _token = data['data']['token'] as String;
        _driver = data['data']['driver'] as Map<String, dynamic>;
        _availability = _driver?['availability'] as String? ?? 'offline';
        _isAuthenticated = true;
        ApiClient().setAuthToken(_token!);
        await _storage.write(key: 'driver_token', value: _token);
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _error = data['error']?['message'] ?? 'Login failed';
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

  Future<void> fetchProfile() async {
    try {
      final response = await ApiClient().get('/driver/me');
      final data = response.data;
      if (data['success'] == true && data['data'] != null) {
        _driver = data['data'];
        _availability = _driver?['availability'] as String? ?? 'offline';
        _isAuthenticated = true;
        notifyListeners();
      } else {
        await logout();
      }
    } catch (_) {
      await logout();
    }
  }

  Future<void> fetchDeliveries() async {
    try {
      final response = await ApiClient().get('/driver/deliveries');
      final data = response.data;
      if (data['success'] == true && data['data'] != null) {
        final list = data['data'] as List<dynamic>;
        _deliveries = list.map((d) => DriverDelivery.fromJson(d)).toList();
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
      final data = response.data;

      if (data['success'] == true) {
        await fetchDeliveries();
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _error = data['error']?['message'] ?? 'Failed to update status';
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
      if (response.data['success'] == true) {
        _availability = status;
        if (status == 'available') await shareCurrentLocation();
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
    shareCurrentLocation();
    _pollTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      fetchDeliveries();
      if (_availability == 'available') shareCurrentLocation();
    });
  }

  void stopPolling() {
    _pollTimer?.cancel();
    _pollTimer = null;
  }

  Future<void> logout() async {
    stopPolling();
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
