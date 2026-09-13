import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../app/providers/cart_provider.dart';
import '../../app/providers/location_provider.dart';
import '../../app/providers/notification_provider.dart';

/// Mandatory customer delivery-location gate. Catalogue routes are not exposed
/// from the normal app flow until the selected GPS/map pin is serviceable.
class LocationSetupScreen extends StatefulWidget {
  const LocationSetupScreen({super.key});

  @override
  State<LocationSetupScreen> createState() => _LocationSetupScreenState();
}

class _LocationSetupScreenState extends State<LocationSetupScreen> {
  bool _gettingGps = false;
  String? _gpsError;

  Future<void> _useCurrentLocation() async {
    setState(() {
      _gettingGps = true;
      _gpsError = null;
    });

    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        throw StateError('Turn on your device location to continue.');
      }
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.deniedForever) {
        throw StateError(
            'Location permission is blocked. Enable it in phone settings.');
      }
      if (permission == LocationPermission.denied) {
        throw StateError('Location permission is needed to check delivery.');
      }

      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
        ),
      );
      if (!mounted) return;

      final accepted = await context.read<LocationProvider>().setAndValidate(
            position.latitude,
            position.longitude,
          );
      if (accepted && mounted) await _continueToMenu();
    } on StateError catch (error) {
      if (mounted) setState(() => _gpsError = error.message.toString());
    } catch (_) {
      if (mounted) {
        setState(() => _gpsError =
            'We could not get your GPS location. Try again or choose it on the map.');
      }
    } finally {
      if (mounted) setState(() => _gettingGps = false);
    }
  }

  Future<void> _continueToMenu() async {
    final cart = context.read<CartProvider>();
    final notifications = context.read<NotificationProvider>();
    await cart.loadCart();
    if (!mounted) return;
    notifications.startPolling();
    if (mounted) context.go('/home');
  }

  @override
  Widget build(BuildContext context) {
    final location = context.watch<LocationProvider>();
    final primary = Theme.of(context).colorScheme.primary;
    final error = _gpsError ?? location.error;

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
                onPressed: _gettingGps || location.isChecking
                    ? null
                    : _useCurrentLocation,
                icon: _gettingGps || location.isChecking
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                            strokeWidth: 2, color: Colors.white),
                      )
                    : const Icon(Icons.my_location_rounded),
                label: Text(_gettingGps || location.isChecking
                    ? 'Checking delivery availability...'
                    : 'Use my current GPS location'),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: _gettingGps || location.isChecking
                    ? null
                    : () => context.push('/location/map'),
                icon: const Icon(Icons.map_outlined),
                label: const Text('Choose a location on map'),
              ),
              const Spacer(),
              Text(
                'Your location is used only to determine delivery availability and fees. You can change it anytime from your profile.',
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
