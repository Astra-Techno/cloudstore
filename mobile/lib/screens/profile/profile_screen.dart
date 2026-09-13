import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../app/providers/auth_provider.dart';
import '../../app/providers/notification_provider.dart';
import '../../services/api_client.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    if (!auth.isAuthenticated) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.person_outline, size: 72, color: Colors.grey[400]),
            const SizedBox(height: 16),
            Text(
              'Not logged in',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(color: Colors.grey[600]),
            ),
            const SizedBox(height: 24),
            FilledButton(
              onPressed: () => context.push('/login'),
              child: const Text('Login'),
            ),
          ],
        ),
      );
    }

    final customer = auth.customer;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          // Profile header
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 32,
                    backgroundColor: Theme.of(context).colorScheme.primaryContainer,
                    child: Text(
                      ((customer?.name.isNotEmpty == true ? customer!.name : 'U'))[0].toUpperCase(),
                      style: TextStyle(
                        fontSize: 28,
                        fontWeight: FontWeight.bold,
                        color: Theme.of(context).colorScheme.primary,
                      ),
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          customer?.name.isNotEmpty == true ? customer!.name : 'Customer',
                          style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          customer?.phone ?? '',
                          style: TextStyle(color: Colors.grey[600]),
                        ),
                        if (customer?.email != null)
                          Text(
                            customer!.email!,
                            style: TextStyle(color: Colors.grey[600]),
                          ),
                        const SizedBox(height: 7),
                        TextButton.icon(
                          onPressed: () => _editProfile(context, auth),
                          icon: const Icon(Icons.edit_outlined, size: 16),
                          label: const Text('Edit profile'),
                          style: TextButton.styleFrom(padding: EdgeInsets.zero, minimumSize: const Size(0, 28)),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),

          const SizedBox(height: 16),

          // Menu items
          Card(
            child: Column(
              children: [
                _menuItem(
                  context,
                  icon: Icons.receipt_long,
                  label: 'My Orders',
                  onTap: () => context.push('/orders'),
                ),
                const Divider(height: 1),
                _menuItem(
                  context,
                  icon: Icons.location_on,
                  label: 'My Addresses',
                  onTap: () => context.push('/addresses'),
                ),
                const Divider(height: 1),
                _menuItem(
                  context,
                  icon: Icons.notifications,
                  label: 'Notifications',
                  trailing: Consumer<NotificationProvider>(
                    builder: (_, np, __) => np.unreadCount > 0
                        ? Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                            decoration: BoxDecoration(
                              color: Colors.red,
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Text(
                              '${np.unreadCount}',
                              style: const TextStyle(color: Colors.white, fontSize: 12),
                            ),
                          )
                        : const SizedBox.shrink(),
                  ),
                  onTap: () => context.push('/notifications'),
                ),
              ],
            ),
          ),

          const SizedBox(height: 16),

          // Legal & Account
          Card(
            child: Column(
              children: [
                _menuItem(
                  context,
                  icon: Icons.privacy_tip_outlined,
                  label: 'Privacy Policy',
                  onTap: () => _showLegalPage(context, 'Privacy Policy', '/legal/privacy-policy'),
                ),
                const Divider(height: 1),
                _menuItem(
                  context,
                  icon: Icons.description_outlined,
                  label: 'Terms of Service',
                  onTap: () => _showLegalPage(context, 'Terms of Service', '/legal/terms'),
                ),
                const Divider(height: 1),
                _menuItem(
                  context,
                  icon: Icons.delete_forever_outlined,
                  label: 'Delete Account',
                  onTap: () => _deleteAccount(context, auth),
                ),
              ],
            ),
          ),

          const SizedBox(height: 16),

          // Logout
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: () {
                context.read<NotificationProvider>().stopPolling();
                auth.logout();
                context.go('/home');
              },
              icon: const Icon(Icons.logout, color: Colors.red),
              label: const Text('Logout', style: TextStyle(color: Colors.red)),
              style: OutlinedButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 14),
                side: const BorderSide(color: Colors.red),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _menuItem(
    BuildContext context, {
    required IconData icon,
    required String label,
    required VoidCallback onTap,
    Widget? trailing,
  }) {
    return ListTile(
      leading: Icon(icon, color: Theme.of(context).colorScheme.primary),
      title: Text(label),
      trailing: trailing ?? const Icon(Icons.chevron_right),
      onTap: onTap,
    );
  }

  Future<void> _editProfile(BuildContext context, AuthProvider auth) async {
    final name = TextEditingController(text: auth.customer?.name ?? '');
    final email = TextEditingController(text: auth.customer?.email ?? '');
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) => Padding(
        padding: EdgeInsets.fromLTRB(20, 8, 20, MediaQuery.of(sheetContext).viewInsets.bottom + 24),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Edit profile', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
          const SizedBox(height: 18),
          TextField(controller: name, textCapitalization: TextCapitalization.words, decoration: const InputDecoration(labelText: 'Name', prefixIcon: Icon(Icons.person_outline))),
          const SizedBox(height: 12),
          TextField(controller: email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email (optional)', prefixIcon: Icon(Icons.email_outlined))),
          const SizedBox(height: 20),
          FilledButton(onPressed: () async {
            final ok = await auth.updateProfile(name: name.text, email: email.text);
            if (sheetContext.mounted && ok) Navigator.pop(sheetContext, true);
          }, child: const Text('Save changes')),
        ]),
      ),
    );
    name.dispose();
    email.dispose();
    if (saved == true && context.mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Profile updated')));
  }

  void _showLegalPage(BuildContext context, String title, String endpoint) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => _LegalContentScreen(title: title, endpoint: endpoint),
      ),
    );
  }

  Future<void> _deleteAccount(BuildContext context, AuthProvider auth) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Account'),
        content: const Text(
          'This action is permanent and cannot be undone. All your data, orders, and addresses will be deleted.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: TextButton.styleFrom(foregroundColor: Colors.red),
            child: const Text('Delete My Account'),
          ),
        ],
      ),
    );

    if (confirmed != true || !context.mounted) return;

    try {
      final response = await ApiClient().post('/customer/me/delete');
      final data = response.data;
      if (data['success'] == true && context.mounted) {
        context.read<NotificationProvider>().stopPolling();
        await auth.logout();
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Account deleted successfully')),
          );
          context.go('/');
        }
      } else if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(data['error']?['message'] ?? 'Failed to delete account'),
          ),
        );
      }
    } catch (_) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to delete account. Please try again.')),
        );
      }
    }
  }
}

class _LegalContentScreen extends StatefulWidget {
  final String title;
  final String endpoint;

  const _LegalContentScreen({required this.title, required this.endpoint});

  @override
  State<_LegalContentScreen> createState() => _LegalContentScreenState();
}

class _LegalContentScreenState extends State<_LegalContentScreen> {
  String? _content;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadContent();
  }

  Future<void> _loadContent() async {
    try {
      final response = await ApiClient().get(widget.endpoint);
      final data = response.data;
      if (data['success'] == true && data['data'] != null) {
        setState(() {
          _content = data['data']['content']?.toString() ??
              data['data'].toString();
        });
      }
    } catch (_) {
      setState(() => _content = 'Unable to load content. Please try again later.');
    } finally {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Text(
                _content ?? '',
                style: const TextStyle(fontSize: 15, height: 1.6),
              ),
            ),
    );
  }
}
