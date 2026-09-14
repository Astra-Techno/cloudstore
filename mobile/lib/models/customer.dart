import 'json_value.dart';

class Customer {
  final String uuid;
  final String name;
  final String phone;
  final String? email;

  Customer({
    required this.uuid,
    required this.name,
    required this.phone,
    this.email,
  });

  factory Customer.fromJson(Map<String, dynamic> json) {
    return Customer(
      uuid: JsonValue.string(json['uuid'] ?? json['id']),
      name: JsonValue.string(json['name']),
      phone: JsonValue.string(json['phone']),
      email: JsonValue.nullableString(json['email']),
    );
  }
}
