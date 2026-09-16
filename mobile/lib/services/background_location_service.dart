import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart';
import 'api_client.dart';

/// Manages background GPS tracking for drivers.
///
/// Starts an Android foreground service to keep the app alive, then uses
/// a periodic timer to poll GPS and push coordinates to the API.
class BackgroundLocationService {
  static final BackgroundLocationService _instance = BackgroundLocationService._();
  factory BackgroundLocationService() => _instance;
  BackgroundLocationService._();

  static const _channel = MethodChannel('cloudmarket/location_service');
  static const _interval = Duration(seconds: 15);

  Timer? _timer;
  bool _running = false;

  bool get isRunning => _running;

  /// Start background location tracking. Requests background location
  /// permission and starts the Android foreground service.
  Future<bool> start() async {
    if (_running) return true;

    // Check and request permissions
    if (!await _ensurePermissions()) return false;

    // Start Android foreground service
    try {
      await _channel.invokeMethod('startService');
    } catch (e) {
      debugPrint('Failed to start location service: $e');
      return false;
    }

    _running = true;

    // Immediately share location, then start periodic updates
    _sendLocation();
    _timer = Timer.periodic(_interval, (_) => _sendLocation());
    return true;
  }

  /// Stop background location tracking.
  Future<void> stop() async {
    _timer?.cancel();
    _timer = null;
    _running = false;

    try {
      await _channel.invokeMethod('stopService');
    } catch (e) {
      debugPrint('Failed to stop location service: $e');
    }
  }

  Future<bool> _ensurePermissions() async {
    // Check if location services are enabled
    if (!await Geolocator.isLocationServiceEnabled()) return false;

    // Request fine location first
    var status = await Permission.locationWhenInUse.status;
    if (status.isDenied) {
      status = await Permission.locationWhenInUse.request();
    }
    if (!status.isGranted) return false;

    // Request background location (Android 10+)
    var bgStatus = await Permission.locationAlways.status;
    if (bgStatus.isDenied) {
      bgStatus = await Permission.locationAlways.request();
    }
    // Background is optional — foreground service still works with whenInUse
    return true;
  }

  Future<void> _sendLocation() async {
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 10),
        ),
      );
      await ApiClient().post('/driver/location', data: {
        'latitude': position.latitude,
        'longitude': position.longitude,
      });
    } catch (e) {
      debugPrint('Background location update failed: $e');
    }
  }
}
