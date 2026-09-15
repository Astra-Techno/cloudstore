import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../app/providers/auth_provider.dart';
import '../../services/api_client.dart';
import '../../widgets/state_widgets.dart';

class SupportScreen extends StatefulWidget {
  const SupportScreen({super.key});

  @override
  State<SupportScreen> createState() => _SupportScreenState();
}

class _SupportScreenState extends State<SupportScreen> {
  List<Map<String, dynamic>> _tickets = const [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await ApiClient().get('/customer/support/tickets');
      final body = response.data;
      if (body is Map && body['success'] == true && body['data'] is List) {
        if (mounted) {
          setState(() => _tickets = (body['data'] as List)
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList());
        }
      } else if (mounted) {
        setState(() => _error = 'Unable to load support requests.');
      }
    } catch (_) {
      if (mounted) setState(() => _error = 'Unable to load support requests.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _createTicket() async {
    final subject = TextEditingController();
    final message = TextEditingController();
    String category = 'other';
    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding: EdgeInsets.fromLTRB(
              20, 8, 20, MediaQuery.of(context).viewInsets.bottom + 24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Align(
              alignment: Alignment.centerLeft,
              child: Text('How can we help?',
                  style: TextStyle(fontSize: 21, fontWeight: FontWeight.w900)),
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              initialValue: category,
              decoration: const InputDecoration(labelText: 'Help topic'),
              items: const [
                DropdownMenuItem(value: 'order', child: Text('Order issue')),
                DropdownMenuItem(
                    value: 'delivery', child: Text('Delivery issue')),
                DropdownMenuItem(
                    value: 'refund', child: Text('Refund or payment')),
                DropdownMenuItem(
                    value: 'account', child: Text('Account or address')),
                DropdownMenuItem(value: 'other', child: Text('Something else')),
              ],
              onChanged: (value) =>
                  setSheetState(() => category = value ?? 'other'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: subject,
              maxLength: 180,
              decoration: const InputDecoration(labelText: 'Subject'),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: message,
              minLines: 3,
              maxLines: 5,
              maxLength: 4000,
              decoration: const InputDecoration(
                labelText: 'Tell us what happened',
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: () async {
                  if (subject.text.trim().length < 3 ||
                      message.text.trim().length < 3) {
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
                        content:
                            Text('Enter a subject and a little more detail.')));
                    return;
                  }
                  try {
                    final response = await ApiClient()
                        .post('/customer/support/tickets', data: {
                      'subject': subject.text.trim(),
                      'message': message.text.trim(),
                      'category': category,
                    });
                    if (response.data is Map &&
                        response.data['success'] == true &&
                        context.mounted) {
                      Navigator.of(context).pop(true);
                    }
                  } catch (_) {
                    if (context.mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
                          content: Text('Unable to send your request.')));
                    }
                  }
                },
                child: const Text('Send request'),
              ),
            ),
          ]),
        ),
      ),
    );
    subject.dispose();
    message.dispose();
    if (created == true && mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(const SnackBar(content: Text('Support request sent')));
      _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!context.watch<AuthProvider>().isAuthenticated) {
      return const Scaffold(
        body: EmptyStateWidget(
          icon: Icons.support_agent_rounded,
          title: 'Log in for help',
          subtitle: 'Your support requests are linked to your account.',
        ),
      );
    }
    return Scaffold(
      appBar: AppBar(title: const Text('Help & support')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _createTicket,
        icon: const Icon(Icons.add_comment_outlined),
        label: const Text('New request'),
      ),
      body: _loading
          ? const LoadingStateWidget(message: 'Loading support requests...')
          : _error != null
              ? ErrorStateWidget(message: _error!, onRetry: _load)
              : _tickets.isEmpty
                  ? EmptyStateWidget(
                      icon: Icons.support_agent_rounded,
                      title: 'How can we help?',
                      subtitle:
                          'Send us a request and the store team can respond here.',
                      actionLabel: 'Start a request',
                      onAction: _createTicket,
                    )
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
                        itemCount: _tickets.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 10),
                        itemBuilder: (context, index) {
                          final ticket = _tickets[index];
                          final status = ticket['status']?.toString() ?? 'open';
                          final primary = Theme.of(context).colorScheme.primary;
                          return Card(
                            child: ListTile(
                              leading: CircleAvatar(
                                backgroundColor: primary.withAlpha(20),
                                child: Icon(Icons.support_agent_rounded,
                                    color: primary),
                              ),
                              title: Text(
                                  ticket['subject']?.toString() ??
                                      'Support request',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w800)),
                              subtitle: Text(
                                  '${ticket['category'] ?? 'other'} · ${status.replaceAll('_', ' ')}'),
                              trailing:
                                  status == 'resolved' || status == 'closed'
                                      ? const Icon(
                                          Icons.check_circle_outline_rounded)
                                      : Icon(Icons.schedule_rounded,
                                          color: primary),
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}
