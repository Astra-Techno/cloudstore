import 'package:cloudstore/models/address.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('Address.fromJson', () {
    test('accepts MySQL decimal strings returned by the API', () {
      final address = Address.fromJson({
        'id': '42',
        'uuid': 'address-uuid',
        'label': 'Home',
        'address_line_1': '20 PKSA Road',
        'city': 'Sivakasi',
        'state': 'Tamil Nadu',
        'postal_code': '626123',
        'latitude': '9.47120380',
        'longitude': '77.76648440',
        'is_default': '1',
      });

      expect(address.id, 42);
      expect(address.latitude, 9.47120380);
      expect(address.longitude, 77.76648440);
      expect(address.isDefault, isTrue);
    });

    test('also accepts native JSON numeric values and boolean defaults', () {
      final address = Address.fromJson({
        'id': 7,
        'uuid': 'address-uuid',
        'address_line_1': 'Market Street',
        'city': 'Sivakasi',
        'postal_code': '626123',
        'latitude': 9.47,
        'longitude': 77.76,
        'is_default': true,
      });

      expect(address.latitude, 9.47);
      expect(address.longitude, 77.76);
      expect(address.isDefault, isTrue);
    });
  });
}
