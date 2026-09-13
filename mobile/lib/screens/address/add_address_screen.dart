import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:dio/dio.dart';
import '../../services/api_client.dart';

class AddAddressScreen extends StatefulWidget {
  const AddAddressScreen({super.key});

  @override
  State<AddAddressScreen> createState() => _AddAddressScreenState();
}

class _AddAddressScreenState extends State<AddAddressScreen> {
  final _formKey = GlobalKey<FormState>();
  final _labelController = TextEditingController(text: 'Home');
  final _line1Controller = TextEditingController();
  final _line2Controller = TextEditingController();
  final _cityController = TextEditingController();
  final _stateController = TextEditingController();
  final _postalCodeController = TextEditingController();
  bool _saving = false;
  bool _locating = false;
  bool _reverseGeocoding = false;
  bool? _deliveryAvailable;
  double? _latitude;
  double? _longitude;
  String? _error;

  @override
  void dispose() {
    _labelController.dispose();
    _line1Controller.dispose();
    _line2Controller.dispose();
    _cityController.dispose();
    _stateController.dispose();
    _postalCodeController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    if (_latitude == null || _longitude == null || _deliveryAvailable != true) {
      setState(() => _error = 'Use your current location and confirm delivery availability first.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final response = await ApiClient().post('/customer/addresses', data: {
        'label': _labelController.text.trim(),
        'address_line_1': _line1Controller.text.trim(),
        'address_line_2': _line2Controller.text.trim().isEmpty ? null : _line2Controller.text.trim(),
        'city': _cityController.text.trim(),
        'state': _stateController.text.trim(),
        'postal_code': _postalCodeController.text.trim(),
        'latitude': _latitude,
        'longitude': _longitude,
      });

      final data = response.data;
      if (data['success'] == true) {
        if (mounted) Navigator.of(context).pop(true);
      } else {
        setState(() => _error = data['error']?['message'] ?? 'Failed to save address');
      }
    } catch (_) {
      setState(() => _error = 'Failed to save address');
    } finally {
      setState(() => _saving = false);
    }
  }

  Future<void> _useCurrentLocation() async {
    setState(() { _locating = true; _error = null; _deliveryAvailable = null; });
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        throw Exception('Turn on Location services to continue.');
      }
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
        throw Exception('Location permission is required for delivery.');
      }
      final position = await Geolocator.getCurrentPosition(desiredAccuracy: LocationAccuracy.high);

      // Check delivery availability with the API
      final response = await ApiClient().post('/customer/addresses/availability', data: {
        'latitude': position.latitude,
        'longitude': position.longitude,
      });
      final availability = response.data['data'];
      if (response.data['success'] != true || availability is! Map || availability['available'] != true) {
        setState(() => _error = 'Sorry, this location is outside the store delivery area.');
      } else {
        setState(() {
          _latitude = position.latitude;
          _longitude = position.longitude;
          _deliveryAvailable = true;
        });
        // Reverse geocode to auto-fill address fields
        _reverseGeocode(position.latitude, position.longitude);
      }
    } catch (error) {
      setState(() => _error = error.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) setState(() => _locating = false);
    }
  }

  /// Calls the Nominatim reverse geocoding API to fill in address fields from coordinates.
  Future<void> _reverseGeocode(double lat, double lon) async {
    setState(() => _reverseGeocoding = true);
    try {
      final dio = Dio();
      final response = await dio.get(
        'https://nominatim.openstreetmap.org/reverse',
        queryParameters: {
          'format': 'json',
          'lat': lat,
          'lon': lon,
          'addressdetails': 1,
          'zoom': 18,
        },
        options: Options(headers: {
          'User-Agent': 'CloudStore/1.0',
          'Accept-Language': 'en',
        }),
      );
      if (response.statusCode == 200 && response.data is Map) {
        final data = response.data as Map<String, dynamic>;
        final address = data['address'] as Map<String, dynamic>? ?? {};

        // Build address_line_1 from available components
        final roadParts = <String>[];
        if (address['house_number'] != null) roadParts.add(address['house_number'].toString());
        if (address['road'] != null) roadParts.add(address['road'].toString());
        if (roadParts.isEmpty && address['neighbourhood'] != null) {
          roadParts.add(address['neighbourhood'].toString());
        }

        // Build address_line_2 from suburb/neighbourhood
        final line2Parts = <String>[];
        if (address['suburb'] != null) line2Parts.add(address['suburb'].toString());
        if (address['neighbourhood'] != null && roadParts.length > 1) {
          line2Parts.add(address['neighbourhood'].toString());
        }

        final city = address['city'] ??
            address['town'] ??
            address['village'] ??
            address['county'] ??
            '';
        final state = address['state'] ?? '';
        final postalCode = address['postcode'] ?? '';

        if (mounted) {
          setState(() {
            if (roadParts.isNotEmpty && _line1Controller.text.isEmpty) {
              _line1Controller.text = roadParts.join(' ');
            }
            if (line2Parts.isNotEmpty && _line2Controller.text.isEmpty) {
              _line2Controller.text = line2Parts.join(', ');
            }
            if (city.toString().isNotEmpty && _cityController.text.isEmpty) {
              _cityController.text = city.toString();
            }
            if (state.toString().isNotEmpty && _stateController.text.isEmpty) {
              _stateController.text = state.toString();
            }
            if (postalCode.toString().isNotEmpty && _postalCodeController.text.isEmpty) {
              _postalCodeController.text = postalCode.toString();
            }
          });
        }
      }
    } catch (_) {
      // Reverse geocoding is best-effort; user can fill fields manually.
    } finally {
      if (mounted) setState(() => _reverseGeocoding = false);
    }
  }

  /// Builds a static map preview URL using OpenStreetMap tiles.
  String _buildStaticMapUrl() {
    if (_latitude == null || _longitude == null) return '';
    final lat = _latitude!;
    final lon = _longitude!;
    const zoom = 16;
    const n = 1 << zoom;
    final x = ((lon + 180) / 360 * n).floor();
    final latRad = lat * math.pi / 180.0;
    final y = ((1 - math.log(math.tan(latRad) + 1 / math.cos(latRad)) / math.pi) / 2 * n).floor();
    return 'https://tile.openstreetmap.org/$zoom/$x/$y.png';
  }

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;

    return Scaffold(
      appBar: AppBar(title: const Text('Add Address')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (_error != null) ...[
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.red.shade50,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.error_outline, color: Colors.red.shade700, size: 20),
                      const SizedBox(width: 8),
                      Expanded(child: Text(_error!, style: TextStyle(color: Colors.red.shade700))),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
              ],

              // Static map preview when coordinates are available
              if (_latitude != null && _longitude != null) ...[
                Container(
                  height: 160,
                  width: double.infinity,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: Colors.grey.shade200),
                    color: Colors.grey.shade100,
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: Stack(
                    children: [
                      // Map tile background
                      Positioned.fill(
                        child: Image.network(
                          _buildStaticMapUrl(),
                          fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => Container(
                            color: primary.withAlpha(18),
                            child: Center(
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.map_outlined, size: 36, color: primary.withAlpha(120)),
                                  const SizedBox(height: 4),
                                  Text(
                                    '${_latitude!.toStringAsFixed(5)}, ${_longitude!.toStringAsFixed(5)}',
                                    style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                      // Pin marker overlay
                      Center(
                        child: Transform.translate(
                          offset: const Offset(0, -14),
                          child: Icon(Icons.location_pin, size: 40, color: Colors.red.shade600),
                        ),
                      ),
                      // Coordinates badge
                      Positioned(
                        bottom: 8,
                        left: 8,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: Colors.black.withAlpha(160),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            '${_latitude!.toStringAsFixed(5)}, ${_longitude!.toStringAsFixed(5)}',
                            style: const TextStyle(color: Colors.white, fontSize: 11),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
              ],

              // Label selector
              Row(
                children: [
                  for (final label in ['Home', 'Work', 'Other'])
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(
                        label: Text(label),
                        selected: _labelController.text == label,
                        onSelected: (_) => setState(() => _labelController.text = label),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 16),

              // Use current location button
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: _locating ? null : _useCurrentLocation,
                  icon: _locating
                      ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                      : Icon(_deliveryAvailable == true ? Icons.check_circle : Icons.my_location),
                  label: Text(
                    _locating
                        ? 'Getting your location...'
                        : _deliveryAvailable == true
                            ? 'Delivery available here'
                            : 'Use my current location',
                  ),
                  style: _deliveryAvailable == true
                      ? OutlinedButton.styleFrom(
                          foregroundColor: Colors.green.shade700,
                          side: BorderSide(color: Colors.green.shade300),
                          backgroundColor: Colors.green.shade50,
                        )
                      : null,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                'Your GPS pin is required to confirm the service area and is saved with this address.',
                style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
              ),

              // Reverse geocoding indicator
              if (_reverseGeocoding) ...[
                const SizedBox(height: 8),
                Row(
                  children: [
                    SizedBox(
                      width: 14,
                      height: 14,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: primary,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      'Looking up your address...',
                      style: TextStyle(fontSize: 12, color: primary),
                    ),
                  ],
                ),
              ],

              const SizedBox(height: 16),
              TextFormField(
                controller: _line1Controller,
                decoration: const InputDecoration(
                  labelText: 'Address Line 1',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.home_outlined),
                ),
                validator: (v) => v == null || v.trim().isEmpty ? 'Required' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _line2Controller,
                decoration: const InputDecoration(
                  labelText: 'Address Line 2 (optional)',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.apartment_outlined),
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _cityController,
                      decoration: const InputDecoration(
                        labelText: 'City',
                        border: OutlineInputBorder(),
                      ),
                      validator: (v) => v == null || v.trim().isEmpty ? 'Required' : null,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextFormField(
                      controller: _stateController,
                      decoration: const InputDecoration(
                        labelText: 'State',
                        border: OutlineInputBorder(),
                      ),
                      validator: (v) => v == null || v.trim().isEmpty ? 'Required' : null,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _postalCodeController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Postal Code',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.pin_drop_outlined),
                ),
                validator: (v) => v == null || v.trim().isEmpty ? 'Required' : null,
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: _saving || _deliveryAvailable != true ? null : _save,
                  style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 16)),
                  child: _saving
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : const Text('Save Address', style: TextStyle(fontSize: 16)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
