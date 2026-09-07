import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../services/api_client.dart';
import '../../models/address.dart';
import '../../widgets/loading_overlay.dart';

class AddressesScreen extends StatefulWidget {
  final bool selectMode;
  const AddressesScreen({super.key, this.selectMode = false});

  @override
  State<AddressesScreen> createState() => _AddressesScreenState();
}

class _AddressesScreenState extends State<AddressesScreen> {
  List<Address> _addresses = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadAddresses();
  }

  Future<void> _loadAddresses() async {
    setState(() => _loading = true);
    try {
      final response = await ApiClient().get('/customer/addresses');
      final data = response.data;
      if (data['success'] == true && data['data'] != null) {
        final list = data['data'] as List<dynamic>;
        setState(() => _addresses = list.map((a) => Address.fromJson(a)).toList());
      }
    } catch (_) {}
    setState(() => _loading = false);
  }

  Future<void> _deleteAddress(String uuid) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Address'),
        content: const Text('Are you sure you want to remove this address?'),
        actions: [
          TextButton(onPressed: () => ctx.pop(false), child: const Text('Cancel')),
          TextButton(onPressed: () => ctx.pop(true), child: const Text('Delete')),
        ],
      ),
    );

    if (confirmed == true) {
      try {
        await ApiClient().delete('/customer/addresses/$uuid');
        _loadAddresses();
      } catch (_) {}
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
          if (result == true) _loadAddresses();
        },
        icon: const Icon(Icons.add),
        label: const Text('Add Address'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
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
                    return Card(
                      margin: const EdgeInsets.only(bottom: 12),
                      child: ListTile(
                        onTap: widget.selectMode ? () => Navigator.of(context).pop(addr) : null,
                        leading: Icon(
                          addr.label.toLowerCase() == 'home'
                              ? Icons.home
                              : addr.label.toLowerCase() == 'work'
                                  ? Icons.work
                                  : Icons.location_on,
                          color: Theme.of(context).colorScheme.primary,
                        ),
                        title: Text(
                          addr.label,
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
                        subtitle: Text(addr.fullAddress, maxLines: 2, overflow: TextOverflow.ellipsis),
                        trailing: widget.selectMode
                            ? const Icon(Icons.chevron_right)
                            : IconButton(
                                icon: const Icon(Icons.delete_outline, color: Colors.red),
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
