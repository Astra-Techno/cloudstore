class Customer {
  final int id;
  final String uuid;
  final String name;
  final String phone;
  final String? email;

  Customer({
    required this.id,
    required this.uuid,
    required this.name,
    required this.phone,
    this.email,
  });

  factory Customer.fromJson(Map<String, dynamic> json) {
    return Customer(
      id: json['id'] as int,
      uuid: json['uuid'] as String,
      name: json['name'] as String,
      phone: json['phone'] as String,
      email: json['email'] as String?,
    );
  }
}
