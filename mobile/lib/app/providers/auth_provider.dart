import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../../services/api_client.dart';
import '../../models/customer.dart';

class AuthProvider extends ChangeNotifier {
  final _storage = const FlutterSecureStorage();
  String? _token;
  Customer? _customer;
  bool _isAuthenticated = false;
  bool _isNewCustomer = false;
  bool _isLoading = false;
  String? _error;

  String? get token => _token;
  Customer? get customer => _customer;
  bool get isAuthenticated => _isAuthenticated;
  bool get isNewCustomer => _isNewCustomer;
  bool get isLoading => _isLoading;
  String? get error => _error;

  bool get needsOnboarding =>
      _isAuthenticated && (_isNewCustomer || _customer?.name == null || _customer!.name.isEmpty);

  Future<void> loadSavedToken() async {
    final savedToken = await _storage.read(key: 'auth_token');
    if (savedToken != null) {
      _token = savedToken;
      ApiClient().setAuthToken(savedToken);
      await fetchProfile();
    }
  }

  Future<bool> requestOtp(String phone) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await ApiClient().post('/customer/otp/request', data: {
        'phone': phone,
      });
      final data = response.data;
      _isLoading = false;
      notifyListeners();
      return data['success'] == true;
    } catch (e) {
      _error = 'Failed to send OTP';
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> verifyOtp(String phone, String otp) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await ApiClient().post('/customer/otp/verify', data: {
        'phone': phone,
        'otp': otp,
      });
      final data = response.data;

      if (data['success'] == true && data['data'] != null) {
        final authData = data['data'];
        _token = authData['token'] as String;
        _customer = Customer.fromJson(authData['customer']);
        _isAuthenticated = true;
        _isNewCustomer = authData['is_new'] == true;

        ApiClient().setAuthToken(_token!);
        await _storage.write(key: 'auth_token', value: _token);

        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _error = data['error']?['message'] ?? 'Invalid OTP';
        _isLoading = false;
        notifyListeners();
        return false;
      }
    } catch (e) {
      _error = 'Verification failed';
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> fetchProfile() async {
    try {
      final response = await ApiClient().get('/customer/me');
      final data = response.data;
      if (data['success'] == true && data['data'] != null) {
        _customer = Customer.fromJson(data['data']);
        _isAuthenticated = true;
        notifyListeners();
      } else {
        await logout();
      }
    } catch (_) {
      await logout();
    }
  }

  Future<void> logout() async {
    _token = null;
    _customer = null;
    _isAuthenticated = false;
    ApiClient().clearAuthToken();
    await _storage.delete(key: 'auth_token');
    notifyListeners();
  }

  Future<bool> updateProfile({String? name, String? email}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final payload = <String, dynamic>{};
      if (name != null && name.trim().isNotEmpty) payload['name'] = name.trim();
      if (email != null && email.trim().isNotEmpty) payload['email'] = email.trim();

      final response = await ApiClient().put('/customer/me', data: payload);
      final data = response.data;

      if (data['success'] == true && data['data'] != null) {
        _customer = Customer.fromJson(data['data']);
        _isNewCustomer = false;
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _error = data['error']?['message'] ?? 'Update failed';
        _isLoading = false;
        notifyListeners();
        return false;
      }
    } catch (e) {
      _error = 'Failed to update profile';
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
