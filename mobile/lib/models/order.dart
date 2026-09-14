import 'json_value.dart';

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
      id: JsonValue.integer(json['id']),
      uuid: JsonValue.string(json['uuid']),
      orderNumber: JsonValue.string(json['order_number']),
      status: JsonValue.string(json['status'], 'pending'),
      orderType: JsonValue.string(json['order_type'], 'delivery'),
      subtotal: JsonValue.integer(json['subtotal']),
      deliveryFee: JsonValue.integer(json['delivery_fee']),
      taxAmount: JsonValue.integer(json['tax_amount']),
      discountAmount: JsonValue.integer(json['discount_amount']),
      total: JsonValue.integer(json['total']),
      paymentMethod: JsonValue.string(json['payment_method'], 'cod'),
      paymentStatus: JsonValue.string(json['payment_status'], 'pending'),
      notes: JsonValue.nullableString(json['notes']),
      addressSnapshot: JsonValue.nullableString(json['address_snapshot']),
      createdAt: JsonValue.string(json['created_at']),
      updatedAt: JsonValue.string(json['updated_at'] ?? json['created_at']),
      items: JsonValue.objectList(json['items']).map(OrderItem.fromJson).toList(),
      statusHistory: JsonValue.objectList(json['status_history'])
          .map(StatusHistoryEntry.fromJson)
          .toList(),
    );
  }

  String get statusDisplay => status.replaceAll('_', ' ');

  bool get isActive =>
      !['delivered', 'cancelled', 'rejected', 'refunded'].contains(status);
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
      id: JsonValue.integer(json['id']),
      productSnapshot: JsonValue.string(json['product_snapshot']),
      variantSnapshot: JsonValue.nullableString(json['variant_snapshot']),
      addonsSnapshot: JsonValue.nullableString(json['addons_snapshot']),
      quantity: JsonValue.integer(json['quantity']),
      unitPrice: JsonValue.integer(json['unit_price']),
      addonsPrice: JsonValue.integer(json['addons_price']),
      lineTotal: JsonValue.integer(json['line_total']),
      notes: JsonValue.nullableString(json['notes']),
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
      fromStatus: JsonValue.nullableString(json['from_status']),
      toStatus: JsonValue.string(json['to_status']),
      actorType: JsonValue.nullableString(json['actor_type']),
      notes: JsonValue.nullableString(json['notes']),
      createdAt: JsonValue.string(json['created_at']),
    );
  }
}
