import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../app/providers/cart_provider.dart';
import '../../app/providers/location_provider.dart';
import '../../app/providers/notification_provider.dart';
import '../../models/address.dart';

/// Mandatory customer delivery-location gate. Catalogue routes are not exposed
/// from the normal app flow until the selected GPS/map pin is serviceable.
class LocationSetupScreen extends StatefulWidget {
  const LocationSetupScreen({super.key});

  @override
  State<LocationSetupScreen> createState() => _LocationSetupScreenState();
}

class _LocationSetupScreenState extends State<LocationSetupScreen> {
  Future<void> _continueToMenu() async {
    final cart = context.read<CartProvider>();
    final notifications = context.read<NotificationProvider>();
    await cart.loadCart();
    if (!mounted) return;
    notifications.startPolling();
    if (mounted) context.go('/home');
  }

  Future<void> _chooseSavedAddress() async {
    final address = await context.push<Address>('/addresses/select');
    if (address != null && mounted) await _continueToMenu();
  }

  Future<void> _addDeliveryAddress() async {
    final address = await context.push<Address>('/address/add');
    if (address != null && mounted) await _continueToMenu();
  }

  @override
  Widget build(BuildContext context) {
    final location = context.watch<LocationProvider>();
    final primary = Theme.of(context).colorScheme.primary;
    final error = location.error;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 32, 24, 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                height: 58,
                width: 58,
                decoration: BoxDecoration(
                  color: primary.withAlpha(20),
                  borderRadius: BorderRadius.circular(18),
                ),
                child:
                    Icon(Icons.location_on_rounded, color: primary, size: 32),
              ),
              const SizedBox(height: 26),
              const Text('Choose your delivery location',
                  style: TextStyle(fontSize: 28, fontWeight: FontWeight.w800)),
              const SizedBox(height: 10),
              Text(
                'We show the menu only after confirming that this store delivers to your exact location.',
                style: TextStyle(
                    color: Colors.grey.shade700, fontSize: 15, height: 1.4),
              ),
              const SizedBox(height: 28),
              if (error != null) ...[
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Colors.red.shade50,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Row(children: [
                    Icon(Icons.info_outline_rounded,
                        color: Colors.red.shade700),
                    const SizedBox(width: 10),
                    Expanded(
                        child: Text(error,
                            style: TextStyle(color: Colors.red.shade800))),
                  ]),
                ),
                const SizedBox(height: 16),
              ],
              FilledButton.icon(
                onPressed: location.isChecking ? null : _addDeliveryAddress,
                icon: const Icon(Icons.add_location_alt_rounded),
                label: const Text('Choose and save delivery address'),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: location.isChecking ? null : _chooseSavedAddress,
                icon: const Icon(Icons.bookmark_outline_rounded),
                label: const Text('Use a saved address'),
              ),
              const Spacer(),
              Text(
                'Choose a saved address to view the menu. You can switch delivery location anytime from your profile.',
                textAlign: TextAlign.center,
                style: TextStyle(
                    color: Colors.grey.shade600, fontSize: 12, height: 1.35),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
