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
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      uuid: json['uuid']?.toString() ?? '',
      label: json['label'] as String? ?? 'Home',
      addressLine1: json['address_line_1']?.toString() ?? '',
      addressLine2: json['address_line_2'] as String?,
      city: json['city']?.toString() ?? '',
      state: json['state'] as String? ?? '',
      postalCode: json['postal_code']?.toString() ?? '',
      // PDO returns DECIMAL columns as strings.  Parsing both JSON numbers
      // and database strings keeps saved addresses usable after a successful
      // API response instead of throwing while the app builds the model.
      latitude: _asDouble(json['latitude']),
      longitude: _asDouble(json['longitude']),
      isDefault: _asBool(json['is_default']),
    );
  }

  static double? _asDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '');
  }

  static bool _asBool(dynamic value) {
    if (value is bool) return value;
    if (value is num) return value != 0;
    return value?.toString() == '1' ||
        value?.toString().toLowerCase() == 'true';
  }

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
