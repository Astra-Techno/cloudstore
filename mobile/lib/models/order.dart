class Order {
  final int id;
  final String uuid;
  final String orderNumber;
  final String status;
  final String orderType;
  final int subtotal;
  final int deliveryFee;
  final int taxAmount;
  final int discountAmount;
  final int total;
  final String paymentMethod;
  final String paymentStatus;
  final String? notes;
  final String? addressSnapshot;
  final String createdAt;
  final String updatedAt;
  final List<OrderItem> items;
  final List<StatusHistoryEntry> statusHistory;

  Order({
    required this.id,
    required this.uuid,
    required this.orderNumber,
    required this.status,
    required this.orderType,
    required this.subtotal,
    required this.deliveryFee,
    required this.taxAmount,
    required this.discountAmount,
    required this.total,
    required this.paymentMethod,
    required this.paymentStatus,
    this.notes,
    this.addressSnapshot,
    required this.createdAt,
    required this.updatedAt,
    this.items = const [],
    this.statusHistory = const [],
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    return Order(
      id: json['id'] as int,
      uuid: json['uuid'] as String,
      orderNumber: json['order_number'] as String,
      status: json['status'] as String,
      orderType: json['order_type'] as String? ?? 'delivery',
      subtotal: json['subtotal'] as int? ?? 0,
      deliveryFee: json['delivery_fee'] as int? ?? 0,
      taxAmount: json['tax_amount'] as int? ?? 0,
      discountAmount: json['discount_amount'] as int? ?? 0,
      total: json['total'] as int? ?? 0,
      paymentMethod: json['payment_method'] as String? ?? 'cod',
      paymentStatus: json['payment_status'] as String? ?? 'pending',
      notes: json['notes'] as String?,
      addressSnapshot: json['address_snapshot'] as String?,
      createdAt: json['created_at'] as String,
      updatedAt: json['updated_at'] as String? ?? json['created_at'] as String,
    );
  }

  String get statusDisplay => status.replaceAll('_', ' ');

  bool get isActive => !['delivered', 'cancelled', 'rejected', 'refunded'].contains(status);
}

class OrderItem {
  final int id;
  final String productSnapshot;
  final String? variantSnapshot;
  final String? addonsSnapshot;
  final int quantity;
  final int unitPrice;
  final int addonsPrice;
  final int lineTotal;
  final String? notes;

  OrderItem({
    required this.id,
    required this.productSnapshot,
    this.variantSnapshot,
    this.addonsSnapshot,
    required this.quantity,
    required this.unitPrice,
    required this.addonsPrice,
    required this.lineTotal,
    this.notes,
  });

  factory OrderItem.fromJson(Map<String, dynamic> json) {
    return OrderItem(
      id: json['id'] as int,
      productSnapshot: json['product_snapshot'] as String,
      variantSnapshot: json['variant_snapshot'] as String?,
      addonsSnapshot: json['addons_snapshot'] as String?,
      quantity: json['quantity'] as int,
      unitPrice: json['unit_price'] as int,
      addonsPrice: json['addons_price'] as int? ?? 0,
      lineTotal: json['line_total'] as int,
      notes: json['notes'] as String?,
    );
  }
}

class StatusHistoryEntry {
  final String? fromStatus;
  final String toStatus;
  final String? actorType;
  final String? notes;
  final String createdAt;

  StatusHistoryEntry({
    this.fromStatus,
    required this.toStatus,
    this.actorType,
    this.notes,
    required this.createdAt,
  });

  factory StatusHistoryEntry.fromJson(Map<String, dynamic> json) {
    return StatusHistoryEntry(
      fromStatus: json['from_status'] as String?,
      toStatus: json['to_status'] as String,
      actorType: json['actor_type'] as String?,
      notes: json['notes'] as String?,
      createdAt: json['created_at'] as String,
    );
  }
}
