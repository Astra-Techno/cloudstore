import 'json_value.dart';

class Address {
  final int id;
  final String uuid;
  final String label;
  final String addressLine1;
  final String? addressLine2;
  final String city;
  final String state;
  final String postalCode;
  final double? latitude;
  final double? longitude;
  final bool isDefault;

  Address({
    required this.id,
    required this.uuid,
    required this.label,
    required this.addressLine1,
    this.addressLine2,
    required this.city,
    required this.state,
    required this.postalCode,
    this.latitude,
    this.longitude,
    this.isDefault = false,
  });

  factory Address.fromJson(Map<String, dynamic> json) {
    return Address(
      id: JsonValue.integer(json['id']),
      uuid: JsonValue.string(json['uuid']),
      label: JsonValue.string(json['label'], 'Home'),
      addressLine1: JsonValue.string(json['address_line_1']),
      addressLine2: JsonValue.nullableString(json['address_line_2']),
      city: JsonValue.string(json['city']),
      state: JsonValue.string(json['state']),
      postalCode: JsonValue.string(json['postal_code']),
      // PDO returns DECIMAL columns as strings.  Parsing both JSON numbers
      // and database strings keeps saved addresses usable after a successful
      // API response instead of throwing while the app builds the model.
      latitude: _asDouble(json['latitude']),
      longitude: _asDouble(json['longitude']),
      isDefault: JsonValue.boolean(json['is_default']),
    );
  }

  static double? _asDouble(dynamic value) => JsonValue.nullableDouble(value);

  Map<String, dynamic> toJson() {
    return {
      'label': label,
      'address_line_1': addressLine1,
      if (addressLine2 != null) 'address_line_2': addressLine2,
      'city': city,
      'state': state,
      'postal_code': postalCode,
      if (latitude != null) 'latitude': latitude,
      if (longitude != null) 'longitude': longitude,
      'is_default': isDefault ? 1 : 0,
    };
  }

  String get fullAddress {
    final parts = [addressLine1];
    if (addressLine2 != null && addressLine2!.isNotEmpty)
      parts.add(addressLine2!);
    parts.add('$city $postalCode');
    return parts.join(', ');
  }
}
