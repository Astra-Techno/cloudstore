import 'package:flutter/material.dart';

class StatusBadge extends StatelessWidget {
  final String status;
  final double fontSize;

  const StatusBadge({super.key, required this.status, this.fontSize = 12});

  static const _colors = {
    'pending_payment': Colors.orange,
    'confirmed': Colors.blue,
    'accepted': Colors.indigo,
    'preparing': Colors.purple,
    'ready': Colors.green,
    'ready_for_pickup': Colors.green,
    'out_for_delivery': Colors.cyan,
    'delivered': Colors.teal,
    'picked_up': Colors.teal,
    'cancelled': Colors.red,
    'rejected': Colors.red,
    'refunded': Colors.grey,
    'assigned': Colors.blue,
    'available': Colors.green,
    'busy': Colors.orange,
    'offline': Colors.grey,
    'active': Colors.green,
    'inactive': Colors.grey,
  };

  @override
  Widget build(BuildContext context) {
    final color = _colors[status] ?? Colors.grey;
    final label = status.replaceAll('_', ' ');

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withAlpha(25),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withAlpha(80)),
      ),
      child: Text(
        label[0].toUpperCase() + label.substring(1),
        style: TextStyle(
          fontSize: fontSize,
          fontWeight: FontWeight.w600,
          color: color,
        ),
      ),
    );
  }
}
