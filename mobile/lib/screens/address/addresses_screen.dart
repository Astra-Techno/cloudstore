import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../services/api_client.dart';
import '../../models/address.dart';
import '../../widgets/loading_overlay.dart';
import 'package:provider/provider.dart';
import '../../app/providers/location_provider.dart';

class AddressesScreen extends StatefulWidget {
  final bool selectMode;
  const AddressesScreen({super.key, this.selectMode = false});

  @override
  State<AddressesScreen> createState() => _AddressesScreenState();
}

class _AddressesScreenState extends State<AddressesScreen> {
  List<Address> _addresses = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadAddresses();
  }

  Future<void> _loadAddresses() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final response = await ApiClient().get('/customer/addresses');
      final data = response.data;
      if (data is Map && data['success'] == true && data['data'] is List) {
        final list = data['data'] as List<dynamic>;
        if (mounted) {
          setState(() => _addresses = list
              .whereType<Map>()
              .map((a) => Address.fromJson(Map<String, dynamic>.from(a)))
              .toList());
        }
      } else if (mounted) {
        final apiError =
            data is Map && data['error'] is Map ? data['error'] as Map : null;
        setState(() => _error = apiError?['message']?.toString() ??
            'Unable to load saved addresses.');
      }
    } catch (_) {
      if (mounted) {
        setState(
            () => _error = 'Unable to load saved addresses. Please try again.');
      }
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _activateAddress(Address address) async {
    final accepted =
        await context.read<LocationProvider>().selectSavedAddress(address);
    if (!mounted) return;
    if (!accepted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
            content: Text(context.read<LocationProvider>().error ??
                'This address cannot be used for delivery.')),
      );
      return;
    }
    if (widget.selectMode) {
      Navigator.of(context).pop(address);
    } else {
      setState(() {});
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Delivery location updated')));
    }
  }

  Future<void> _deleteAddress(String uuid) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Address'),
        content: const Text('Are you sure you want to remove this address?'),
        actions: [
          TextButton(
              onPressed: () => ctx.pop(false), child: const Text('Cancel')),
          TextButton(
              onPressed: () => ctx.pop(true), child: const Text('Delete')),
        ],
      ),
    );

    if (confirmed == true) {
      try {
        final response = await ApiClient().delete('/customer/addresses/$uuid');
        if (response.data is Map && response.data['success'] == true) {
          await _loadAddresses();
        } else if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Unable to delete this address.')));
        }
      } catch (_) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Unable to delete this address. Please try again.')));
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.selectMode ? 'Select Address' : 'My Addresses'),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          final result = await context.push('/address/add');
          if (result != null) _loadAddresses();
        },
        icon: const Icon(Icons.add),
        label: const Text('Add Address'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(mainAxisSize: MainAxisSize.min, children: [
                        Text(_error!, textAlign: TextAlign.center),
                        const SizedBox(height: 12),
                        FilledButton(
                            onPressed: _loadAddresses,
                            child: const Text('Retry'))
                      ])))
              : _addresses.isEmpty
                  ? const EmptyState(
                      icon: Icons.location_off,
                      title: 'No saved addresses',
                      subtitle: 'Add an address for delivery',
                    )
                  : ListView.builder(
                      padding: const EdgeInsets.all(16),
                      itemCount: _addresses.length,
                      itemBuilder: (context, index) {
                        final addr = _addresses[index];
                        final isActive = context
                                .watch<LocationProvider>()
                                .activeAddressUuid ==
                            addr.uuid;
                        return Card(
                          margin: const EdgeInsets.only(bottom: 12),
                          child: ListTile(
                            onTap: () => _activateAddress(addr),
                            leading: Icon(
                              addr.label.toLowerCase() == 'home'
                                  ? Icons.home
                                  : addr.label.toLowerCase() == 'work'
                                      ? Icons.work
                                      : Icons.location_on,
                              color: Theme.of(context).colorScheme.primary,
                            ),
                            title: Row(
                              children: [
                                Text(addr.label,
                                    style: const TextStyle(
                                        fontWeight: FontWeight.w600)),
                                if (addr.isDefault || isActive) ...[
                                  const SizedBox(width: 8),
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                        horizontal: 7, vertical: 3),
                                    decoration: BoxDecoration(
                                      color: Theme.of(context)
                                          .colorScheme
                                          .primary
                                          .withAlpha(18),
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Text(
                                      isActive ? 'Selected' : 'Default',
                                      style: TextStyle(
                                          fontSize: 10,
                                          fontWeight: FontWeight.w700,
                                          color: Theme.of(context)
                                              .colorScheme
                                              .primary),
                                    ),
                                  ),
                                ],
                              ],
                            ),
                            subtitle: Text(addr.fullAddress,
                                maxLines: 2, overflow: TextOverflow.ellipsis),
                            trailing: widget.selectMode
                                ? const Icon(Icons.chevron_right)
                                : IconButton(
                                    icon: const Icon(Icons.delete_outline,
                                        color: Colors.red),
                                    onPressed: () => _deleteAddress(addr.uuid),
                                  ),
                            isThreeLine: true,
                          ),
                        );
                      },
                    ),
    );
  }
}
