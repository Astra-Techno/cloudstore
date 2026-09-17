import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../../app/providers/driver_provider.dart';
import '../../widgets/price_text.dart';
import '../../widgets/status_badge.dart';
import '../../services/device_actions.dart';

class DeliveryDetailScreen extends StatelessWidget {
  final int assignmentId;
  const DeliveryDetailScreen({super.key, required this.assignmentId});

  @override
  Widget build(BuildContext context) {
    final driver = context.watch<DriverProvider>();
    final delivery =
        driver.deliveries.where((d) => d.id == assignmentId).firstOrNull;

    if (delivery == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Delivery')),
        body: const Center(child: Text('Delivery not found')),
      );
    }

    return Scaffold(
      appBar: AppBar(
          title: Text(delivery.orderNumber ?? 'Delivery #${delivery.id}')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Status
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Status', style: TextStyle(fontSize: 16)),
                    StatusBadge(status: delivery.status, fontSize: 14),
                  ],
                ),
              ),
            ),

            // Delivery OTP (shown prominently when picked up)
            if (delivery.deliveryOtp != null &&
                delivery.status == 'picked_up') ...[
              const SizedBox(height: 12),
              Card(
                color: Theme.of(context).colorScheme.primaryContainer,
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    children: [
                      Text(
                        'Delivery OTP',
                        style: TextStyle(
                          fontSize: 14,
                          color:
                              Theme.of(context).colorScheme.onPrimaryContainer,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        delivery.deliveryOtp!,
                        style: TextStyle(
                          fontSize: 36,
                          fontWeight: FontWeight.bold,
                          letterSpacing: 12,
                          color:
                              Theme.of(context).colorScheme.onPrimaryContainer,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Ask the customer for this code to confirm delivery',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 13,
                          color: Theme.of(context)
                              .colorScheme
                              .onPrimaryContainer
                              .withValues(alpha: 0.7),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],

            if (delivery.customerPhone != null ||
                delivery.deliveryAddress != null) ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  if (delivery.customerPhone != null)
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () =>
                            _callCustomer(context, delivery.customerPhone!),
                        icon: const Icon(Icons.call_rounded),
                        label: const Text('Call customer'),
                      ),
                    ),
                  if (delivery.customerPhone != null &&
                      delivery.deliveryAddress != null)
                    const SizedBox(width: 10),
                  if (delivery.deliveryAddress != null)
                    Expanded(
                      child: FilledButton.tonalIcon(
                        onPressed: () =>
                            _openDirections(context, delivery.deliveryAddress!),
                        icon: const Icon(Icons.navigation_rounded),
                        label: const Text('Directions'),
                      ),
                    ),
                ],
              ),
            ],

            // Customer info
            const SizedBox(height: 16),
            Text('Customer', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    if (delivery.customerName != null)
                      _infoRow(Icons.person, 'Name', delivery.customerName!),
                    if (delivery.customerPhone != null)
                      _infoRow(Icons.phone, 'Phone', delivery.customerPhone!),
                    if (delivery.deliveryAddress != null)
                      _infoRow(Icons.location_on, 'Address',
                          delivery.deliveryAddress!),
                  ],
                ),
              ),
            ),

            // Order info
            if (delivery.orderTotal != null) ...[
              const SizedBox(height: 16),
              Text('Order', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      _infoRow(Icons.receipt, 'Order Number',
                          delivery.orderNumber ?? '#${delivery.orderId}'),
                      _infoRow(Icons.currency_rupee, 'Total',
                          PriceText.format(delivery.orderTotal!)),
                      if (delivery.orderStatus != null)
                        _infoRow(Icons.info_outline, 'Order Status',
                            delivery.orderStatus!.replaceAll('_', ' ')),
                    ],
                  ),
                ),
              ),
            ],

            // Timeline
            const SizedBox(height: 16),
            Text('Timeline', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    _timelineItem('Assigned', delivery.assignedAt, true),
                    _timelineItem('Accepted', delivery.acceptedAt,
                        delivery.acceptedAt != null),
                    _timelineItem('Picked Up', delivery.pickedUpAt,
                        delivery.pickedUpAt != null),
                    _timelineItem('Delivered', delivery.deliveredAt,
                        delivery.deliveredAt != null),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 32),
          ],
        ),
      ),

      // Action button
      bottomNavigationBar: delivery.nextStatusLabel != null
          ? SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: FilledButton(
                  onPressed: driver.isLoading
                      ? null
                      : () async {
                          if (delivery.requiresOtpVerification) {
                            _showOtpVerificationDialog(
                                context, driver, delivery.id);
                          } else {
                            final success = await driver.updateDeliveryStatus(
                              delivery.id,
                              delivery.nextStatus!,
                            );
                            if (!success && context.mounted) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(
                                  content: Text(driver.error ??
                                      'Failed to update delivery.'),
                                  backgroundColor: Colors.red,
                                ),
                              );
                            }
                          }
                        },
                  style: FilledButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                  ),
                  child: driver.isLoading
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white),
                        )
                      : Text(
                          delivery.nextStatusLabel!,
                          style: const TextStyle(fontSize: 16),
                        ),
                ),
              ),
            )
          : null,
    );
  }

  void _showOtpVerificationDialog(
      BuildContext context, DriverProvider driver, int assignmentId) {
    final otpController = TextEditingController();

    showDialog(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          title: const Text('Enter Delivery OTP'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'Ask the customer for the 4-digit delivery OTP to confirm handoff.',
              ),
              const SizedBox(height: 16),
              TextField(
                controller: otpController,
                keyboardType: TextInputType.number,
                maxLength: 4,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.bold,
                  letterSpacing: 8,
                ),
                decoration: const InputDecoration(
                  hintText: '----',
                  counterText: '',
                  border: OutlineInputBorder(),
                ),
                autofocus: true,
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () async {
                final otp = otpController.text.trim();
                if (otp.length != 4) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Please enter a 4-digit OTP')),
                  );
                  return;
                }

                Navigator.of(dialogContext).pop();

                final success =
                    await driver.verifyDeliveryOtp(assignmentId, otp);

                if (context.mounted) {
                  if (success) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Delivery completed!'),
                        backgroundColor: Colors.green,
                      ),
                    );
                    Navigator.of(context).pop();
                  } else {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content:
                            Text(driver.error ?? 'OTP verification failed'),
                        backgroundColor: Colors.red,
                      ),
                    );
                  }
                }
              },
              child: const Text('Verify'),
            ),
          ],
        );
      },
    );
  }

  Widget _infoRow(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: Colors.grey),
          const SizedBox(width: 8),
          SizedBox(
            width: 80,
            child: Text(label, style: TextStyle(color: Colors.grey[600])),
          ),
          Expanded(
            child: Text(value,
                style: const TextStyle(fontWeight: FontWeight.w500)),
          ),
        ],
      ),
    );
  }

  Future<void> _callCustomer(BuildContext context, String phone) async {
    final launched = await DeviceActions.call(phone);
    if (!launched && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not open the phone app.')));
    }
  }

  Future<void> _openDirections(BuildContext context, String address) async {
    final launched = await DeviceActions.openDirections(address);
    if (!launched && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not open directions.')));
    }
  }

  Widget _timelineItem(String label, String? timestamp, bool isActive) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          Container(
            width: 12,
            height: 12,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: isActive ? Colors.green : Colors.grey[300],
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                fontWeight: isActive ? FontWeight.w600 : FontWeight.normal,
                color: isActive ? Colors.black : Colors.grey,
              ),
            ),
          ),
          if (timestamp != null)
            Text(
              DateFormat('hh:mm a').format(DateTime.parse(timestamp)),
              style: TextStyle(color: Colors.grey[600], fontSize: 13),
            ),
        ],
      ),
    );
  }
}
