import 'dart:convert';

import 'json_value.dart';

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
  final String? deliveryOtp;
  final int? earnings;

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
    this.deliveryOtp,
    this.earnings,
  });

  factory DriverDelivery.fromJson(Map<String, dynamic> json) {
    return DriverDelivery(
      id: JsonValue.integer(json['id']),
      orderId: JsonValue.integer(json['order_id']),
      orderNumber: JsonValue.nullableString(json['order_number']),
      orderStatus: JsonValue.nullableString(json['order_status']),
      orderTotal: JsonValue.nullableInt(json['order_total']),
      customerName: JsonValue.nullableString(json['customer_name']),
      customerPhone: JsonValue.nullableString(json['customer_phone']),
      deliveryAddress:
          _formatAddress(JsonValue.nullableString(json['delivery_address'])),
      status: JsonValue.string(json['status'], 'assigned'),
      assignedAt: JsonValue.nullableString(json['assigned_at']),
      acceptedAt: JsonValue.nullableString(json['accepted_at']),
      pickedUpAt: JsonValue.nullableString(json['picked_up_at']),
      deliveredAt: JsonValue.nullableString(json['delivered_at']),
      deliveryOtp: JsonValue.nullableString(json['delivery_otp']),
      earnings: JsonValue.nullableInt(json['earnings']),
    );
  }

  static String? _formatAddress(String? raw) {
    if (raw == null || raw.isEmpty) return null;
    try {
      final address = jsonDecode(raw) as Map<String, dynamic>;
      return [address['address_line_1'], address['address_line_2'], address['landmark'], address['city'], address['postal_code']]
          .where((part) => part != null && part.toString().trim().isNotEmpty)
          .join(', ');
    } catch (_) {
      return raw;
    }
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
        return 'Verify & Complete Delivery';
      default:
        return null;
    }
  }

  bool get requiresOtpVerification => status == 'picked_up';
}
