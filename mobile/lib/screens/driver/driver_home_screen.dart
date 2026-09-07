import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../app/providers/driver_provider.dart';
import '../../widgets/price_text.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/loading_overlay.dart';

class DriverHomeScreen extends StatefulWidget {
  const DriverHomeScreen({super.key});

  @override
  State<DriverHomeScreen> createState() => _DriverHomeScreenState();
}

class _DriverHomeScreenState extends State<DriverHomeScreen> {
  @override
  void initState() {
    super.initState();
    final driver = context.read<DriverProvider>();
    driver.startPolling();
  }

  @override
  Widget build(BuildContext context) {
    final driver = context.watch<DriverProvider>();

    return Scaffold(
      appBar: AppBar(
        title: Text(driver.driver?['name'] ?? 'Driver'),
        actions: [
          // Availability toggle
          PopupMenuButton<String>(
            onSelected: (value) => driver.setAvailability(value),
            itemBuilder: (_) => [
              const PopupMenuItem(value: 'available', child: Text('Available')),
              const PopupMenuItem(value: 'busy', child: Text('Busy')),
              const PopupMenuItem(value: 'offline', child: Text('Offline')),
            ],
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: StatusBadge(status: driver.availability),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () {
              driver.logout();
              context.go('/driver/login');
            },
          ),
        ],
      ),
      body: driver.deliveries.isEmpty
          ? const EmptyState(
              icon: Icons.delivery_dining,
              title: 'No deliveries',
              subtitle: 'New delivery assignments will appear here',
            )
          : RefreshIndicator(
              onRefresh: driver.fetchDeliveries,
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: driver.deliveries.length,
                itemBuilder: (context, index) {
                  final delivery = driver.deliveries[index];
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: InkWell(
                      borderRadius: BorderRadius.circular(12),
                      onTap: () => context.push('/driver/delivery/${delivery.id}'),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  delivery.orderNumber ?? '#${delivery.orderId}',
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                                ),
                                StatusBadge(status: delivery.status),
                              ],
                            ),
                            const SizedBox(height: 8),
                            if (delivery.customerName != null)
                              Row(
                                children: [
                                  Icon(Icons.person, size: 16, color: Colors.grey[600]),
                                  const SizedBox(width: 4),
                                  Text(delivery.customerName!, style: TextStyle(color: Colors.grey[700])),
                                ],
                              ),
                            if (delivery.deliveryAddress != null) ...[
                              const SizedBox(height: 4),
                              Row(
                                children: [
                                  Icon(Icons.location_on, size: 16, color: Colors.grey[600]),
                                  const SizedBox(width: 4),
                                  Expanded(
                                    child: Text(
                                      delivery.deliveryAddress!,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(color: Colors.grey[600], fontSize: 13),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                            if (delivery.orderTotal != null) ...[
                              const SizedBox(height: 8),
                              Text(
                                PriceText.format(delivery.orderTotal!),
                                style: TextStyle(
                                  fontWeight: FontWeight.bold,
                                  color: Theme.of(context).colorScheme.primary,
                                ),
                              ),
                            ],
                            if (delivery.nextStatusLabel != null) ...[
                              const SizedBox(height: 12),
                              SizedBox(
                                width: double.infinity,
                                child: FilledButton(
                                  onPressed: driver.isLoading
                                      ? null
                                      : () => driver.updateDeliveryStatus(
                                            delivery.id,
                                            delivery.nextStatus!,
                                          ),
                                  child: Text(delivery.nextStatusLabel!),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
    );
  }
}
