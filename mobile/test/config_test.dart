import 'package:flutter_test/flutter_test.dart';
import 'package:cloudstore/config/app_config.dart';

void main() {
  group('AppConfig', () {
    test('default app mode is customer', () {
      expect(AppConfig.appMode, 'customer');
    });

    test('default app name is CloudMarket', () {
      expect(AppConfig.appName, 'CloudMarket');
    });

    test('fallback primary color is valid', () {
      final color = AppConfig.fallbackPrimaryColor;
      expect(color.a, greaterThan(0));
    });

    test('assetUrl returns absolute URLs unchanged', () {
      expect(AppConfig.assetUrl('https://example.com/img.png'), 'https://example.com/img.png');
      expect(AppConfig.assetUrl('http://localhost/img.png'), 'http://localhost/img.png');
    });

    test('assetUrl prepends base URL authority to relative paths', () {
      final url = AppConfig.assetUrl('/uploads/logo.png');
      expect(url, contains('/uploads/logo.png'));
      expect(url, startsWith('http'));
    });

    test('assetUrl returns non-slash strings unchanged', () {
      expect(AppConfig.assetUrl('inline-data'), 'inline-data');
    });
  });
}
