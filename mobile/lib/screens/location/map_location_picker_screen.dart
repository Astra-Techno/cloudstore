import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';

import '../../app/providers/cart_provider.dart';
import '../../app/providers/location_provider.dart';
import '../../app/providers/notification_provider.dart';
import '../../config/app_config.dart';

/// Free OpenStreetMap pin picker. It supplies the same precise coordinates as
/// a commercial map picker without requiring a Google Maps billing key.
class MapLocationPickerScreen extends StatefulWidget {
  final bool returnLocation;
  const MapLocationPickerScreen({super.key, this.returnLocation = false});

  @override
  State<MapLocationPickerScreen> createState() =>
      _MapLocationPickerScreenState();
}

class _MapLocationPickerScreenState extends State<MapLocationPickerScreen> {
  late LatLng _pin;

  @override
  void initState() {
    super.initState();
    final selected = context.read<LocationProvider>();
    _pin = LatLng(selected.latitude ?? 9.9252, selected.longitude ?? 78.1198);
  }

  Future<void> _confirmPin() async {
    if (widget.returnLocation) {
      context.pop(<String, double>{
        'latitude': _pin.latitude,
        'longitude': _pin.longitude,
      });
      return;
    }
    final location = context.read<LocationProvider>();
    final cart = context.read<CartProvider>();
    final notifications = context.read<NotificationProvider>();
    final accepted =
        await location.setAndValidate(_pin.latitude, _pin.longitude);
    if (!accepted || !mounted) return;

    await cart.loadCart();
    if (!mounted) return;
    notifications.startPolling();
    if (mounted) context.go('/home');
  }

  @override
  Widget build(BuildContext context) {
    final location = context.watch<LocationProvider>();
    final primary = Theme.of(context).colorScheme.primary;
    return Scaffold(
      appBar: AppBar(
          title: Text(widget.returnLocation
              ? 'Choose address on map'
              : 'Choose delivery location')),
      body: Stack(children: [
        FlutterMap(
          options: MapOptions(
            initialCenter: _pin,
            initialZoom: 15,
            onTap: (_, point) => setState(() => _pin = point),
          ),
          children: [
            TileLayer(
              urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
              userAgentPackageName: 'com.cloudmarket.${AppConfig.appMode}',
            ),
            MarkerLayer(markers: [
              Marker(
                point: _pin,
                width: 52,
                height: 52,
                child: Icon(Icons.location_pin, color: primary, size: 52),
              ),
            ]),
          ],
        ),
        Positioned(
          left: 16,
          right: 16,
          bottom: 20,
          child: SafeArea(
            top: false,
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              if (location.error != null) ...[
                Container(
                  width: double.infinity,
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.red.shade50,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Text(location.error!,
                      style: TextStyle(color: Colors.red.shade800)),
                ),
              ],
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16)),
                child: Text(
                  widget.returnLocation
                      ? 'Move the map and tap your exact address pin. We will check delivery before saving it.'
                      : 'Move the map and tap your exact location. We will check delivery before showing the menu.',
                  style: TextStyle(color: Colors.grey.shade700, height: 1.3),
                ),
              ),
              const SizedBox(height: 10),
              FilledButton.icon(
                onPressed: location.isChecking ? null : _confirmPin,
                icon: location.isChecking
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                            strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.check_circle_outline),
                label: Text(location.isChecking
                    ? 'Checking delivery availability...'
                    : widget.returnLocation
                        ? 'Use this address pin'
                        : 'Confirm this location'),
              ),
            ]),
          ),
        ),
      ]),
    );
  }
}
