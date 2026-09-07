class DriverDelivery {
  final int id;
  final int orderId;
  final String? orderNumber;
  final String? orderStatus;
  final int? orderTotal;
  final String? customerName;
  final String? customerPhone;
  final String? deliveryAddress;
  final String status;
  final String? assignedAt;
  final String? acceptedAt;
  final String? pickedUpAt;
  final String? deliveredAt;

  DriverDelivery({
    required this.id,
    required this.orderId,
    this.orderNumber,
    this.orderStatus,
    this.orderTotal,
    this.customerName,
    this.customerPhone,
    this.deliveryAddress,
    required this.status,
    this.assignedAt,
    this.acceptedAt,
    this.pickedUpAt,
    this.deliveredAt,
  });

  factory DriverDelivery.fromJson(Map<String, dynamic> json) {
    return DriverDelivery(
      id: json['id'] as int,
      orderId: json['order_id'] as int,
      orderNumber: json['order_number'] as String?,
      orderStatus: json['order_status'] as String?,
      orderTotal: json['order_total'] as int?,
      customerName: json['customer_name'] as String?,
      customerPhone: json['customer_phone'] as String?,
      deliveryAddress: json['delivery_address'] as String?,
      status: json['status'] as String,
      assignedAt: json['assigned_at'] as String?,
      acceptedAt: json['accepted_at'] as String?,
      pickedUpAt: json['picked_up_at'] as String?,
      deliveredAt: json['delivered_at'] as String?,
    );
  }

  String get statusDisplay => status.replaceAll('_', ' ');

  String? get nextStatus {
    switch (status) {
      case 'assigned':
        return 'accepted';
      case 'accepted':
        return 'picked_up';
      case 'picked_up':
        return 'delivered';
      default:
        return null;
    }
  }

  String? get nextStatusLabel {
    switch (status) {
      case 'assigned':
        return 'Accept Delivery';
      case 'accepted':
        return 'Mark Picked Up';
      case 'picked_up':
        return 'Mark Delivered';
      default:
        return null;
    }
  }
}
