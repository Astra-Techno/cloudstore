import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../services/api_client.dart';

/// Holds the customer's currently selected delivery pin.
///
/// A pin is usable only after the tenant API confirms its delivery zone. This
/// prevents a catalogue being shown for a store that cannot serve the customer.
class LocationProvider extends ChangeNotifier {
  static const _latitudeKey = 'active_delivery_latitude';
  static const _longitudeKey = 'active_delivery_longitude';

  double? _latitude;
  double? _longitude;
  bool _isChecking = false;
  bool _isServiceable = false;
  String? _error;

  double? get latitude => _latitude;
  double? get longitude => _longitude;
  bool get isChecking => _isChecking;
  bool get isServiceable => _isServiceable;
  bool get hasConfirmedLocation =>
      _isServiceable && _latitude != null && _longitude != null;
  String? get error => _error;

  /// Restores the last customer pin and rechecks it with the active store.
  /// Service zones can change, so a cached pin alone never unlocks the menu.
  Future<bool> restoreAndValidate() async {
    final preferences = await SharedPreferences.getInstance();
    final latitude = preferences.getDouble(_latitudeKey);
    final longitude = preferences.getDouble(_longitudeKey);
    if (latitude == null || longitude == null) {
      _isServiceable = false;
      return false;
    }

    return setAndValidate(latitude, longitude, persist: false);
  }

  Future<bool> setAndValidate(
    double latitude,
    double longitude, {
    bool persist = true,
  }) async {
    _isChecking = true;
    _isServiceable = false;
    _error = null;
    notifyListeners();

    try {
      final response = await ApiClient().post(
        '/customer/addresses/availability',
        data: {'latitude': latitude, 'longitude': longitude},
      );
      final body = response.data;
      final data = body is Map ? body['data'] : null;
      final available = body is Map && body['success'] == true && data is Map
          ? data['available'] == true
          : false;

      if (!available) {
        _error = body is Map && body['error'] is Map
            ? (body['error']['message']?.toString() ??
                'This location is outside the delivery area.')
            : 'This location is outside the delivery area.';
        return false;
      }

      _latitude = latitude;
      _longitude = longitude;
      _isServiceable = true;
      if (persist) {
        final preferences = await SharedPreferences.getInstance();
        await preferences.setDouble(_latitudeKey, latitude);
        await preferences.setDouble(_longitudeKey, longitude);
      }
      return true;
    } on DioException catch (error) {
      final responseData = error.response?.data;
      final apiError = responseData is Map && responseData['error'] is Map
          ? responseData['error'] as Map
          : null;
      _error = apiError?['message']?.toString() ??
          (error.response?.statusCode != null &&
                  error.response!.statusCode! >= 500
              ? 'We could not confirm delivery availability right now. Please try again.'
              : 'Unable to check this location. Check your connection and try again.');
      return false;
    } catch (_) {
      _error = 'Unable to check this location. Please try again.';
      return false;
    } finally {
      _isChecking = false;
      notifyListeners();
    }
  }

  Future<void> clear() async {
    _latitude = null;
    _longitude = null;
    _isServiceable = false;
    _error = null;
    final preferences = await SharedPreferences.getInstance();
    await preferences.remove(_latitudeKey);
    await preferences.remove(_longitudeKey);
    notifyListeners();
  }
}
