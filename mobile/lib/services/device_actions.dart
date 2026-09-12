import 'package:flutter/services.dart';

/// Small Android bridge for actions which must leave the driver app. Keeping
/// this in-house avoids tying a white-label build to an incompatible plugin.
class DeviceActions {
  static const _channel = MethodChannel('cloudmarket/device_actions');

  static Future<bool> call(String phone) async {
    final sanitized = phone.replaceAll(RegExp(r'[^0-9+]'), '');
    return await _invoke('call', {'phone': sanitized});
  }

  static Future<bool> openDirections(String address) async {
    return await _invoke('directions', {'address': address});
  }

  static Future<bool> _invoke(
      String method, Map<String, String> arguments) async {
    try {
      return await _channel.invokeMethod<bool>(method, arguments) ?? false;
    } on PlatformException {
      return false;
    } on MissingPluginException {
      return false;
    }
  }
}
