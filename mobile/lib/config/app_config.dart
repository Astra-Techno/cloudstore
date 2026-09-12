import 'package:flutter/material.dart';

class AppConfig {
  static const String appToken = String.fromEnvironment(
    'APP_TOKEN',
    defaultValue: '',
  );

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );

  static const String appMode = String.fromEnvironment(
    'APP_MODE',
    defaultValue: 'customer', // 'customer', 'driver', or 'marketplace'
  );

  /// Supplied at build time for white-label installs so the first frame is
  /// already store-branded before the bootstrap request completes.
  static const String appName = String.fromEnvironment(
    'APP_NAME',
    defaultValue: 'CloudMarket',
  );

  static const String primaryColorHex = String.fromEnvironment(
    'PRIMARY_COLOR',
    defaultValue: '#E23744',
  );

  static Color get fallbackPrimaryColor {
    final hex = primaryColorHex.replaceFirst('#', '');
    final value = hex.length == 6 ? int.tryParse('FF$hex', radix: 16) : null;
    return value == null ? const Color(0xFFE23744) : Color(value);
  }

  /// API image URLs are stored as /uploads/...; Android needs an absolute URL.
  static String assetUrl(String url) {
    if (url.startsWith('http://') || url.startsWith('https://')) return url;
    if (!url.startsWith('/')) return url;
    final uri = Uri.parse(apiBaseUrl);
    return '${uri.scheme}://${uri.authority}$url';
  }
}
