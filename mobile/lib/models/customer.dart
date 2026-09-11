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
      uuid: (json['uuid'] ?? json['id'] ?? '').toString(),
      name: (json['name'] ?? '') as String,
      phone: (json['phone'] ?? '') as String,
      email: json['email'] as String?,
    );
  }
}
