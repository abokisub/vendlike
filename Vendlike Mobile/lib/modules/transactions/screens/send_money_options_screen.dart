import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:provider/provider.dart';
import '../../../theme/app_colors.dart';
import '../../../services/auth_service.dart';

class SendMoneyOptionsScreen extends StatelessWidget {
  const SendMoneyOptionsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final authService = Provider.of<AuthService>(context);
    final settings = authService.appSettings;
    
    // Check if internal transfer is enabled (default to true if setting not found)
    final internalTransferEnabled = settings?['internal_transfer_enabled'] == 1 || 
                                   settings?['internal_transfer_enabled'] == '1' || 
                                   settings?['internal_transfer_enabled'] == true ||
                                   settings?['internal_transfer_enabled'] == null;
    return PopScope(
      canPop: context.canPop(),
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) return;
        // If stuck, go to dashboard
        context.go('/dashboard');
      },
      child: Scaffold(
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        appBar: AppBar(
          backgroundColor: Theme.of(context).scaffoldBackgroundColor,
          elevation: 0,
          leading: IconButton(
          icon: Icon(Icons.arrow_back_ios_new_rounded, color: Theme.of(context).colorScheme.onSurface),
          onPressed: () => context.go('/dashboard'),
        ),
        title: Text(
          'Transfer',
          style: GoogleFonts.poppins(
            color: Theme.of(context).colorScheme.onSurface,
            fontWeight: FontWeight.bold,
            fontSize: 18,
          ),
        ),
        centerTitle: true,
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Choose Transfer Method',
                style: GoogleFonts.inter(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
                ),
              ).animate().fadeIn().moveX(begin: -10, end: 0),
              
              const SizedBox(height: 16),

              // Only show Vendlike Account option if internal transfer is enabled
              if (internalTransferEnabled) ...[
                _buildOptionCard(
                  context,
                  imagePath: 'assets/images/logo.png',
                  title: 'Vendlike Account',
                  subtitle: 'Transfer money to other Vendlike users',
                  color: AppColors.primary, // Green Theme
                  delay: 100,
                  onTap: () => context.push('/internal-transfer'),
                ),
                
                const SizedBox(height: 16),
              ],

              _buildOptionCard(
                context,
                icon: Icons.account_balance_rounded,
                title: 'Other Banks',
                subtitle: 'Send to any local bank account',
                color: AppColors.primary, // Green Theme
                delay: 200,
                onTap: () => context.push('/bank-transfer'),
              ),
              
              const SizedBox(height: 16),

              _buildOptionCard(
                context,
                icon: Icons.public_rounded,
                title: 'Crossboarder Transfer',
                subtitle: 'Send money internationally',
                color: AppColors.primary, // Green Theme
                delay: 300,
                onTap: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('International transfers coming soon!'))
                  );
                },
              ),
            ],
          ),
        ),
      ),
    ));
  }

  Widget _buildOptionCard(
    BuildContext context, {
    IconData? icon,
    String? imagePath,
    required String title,
    required String subtitle,
    required Color color,
    required int delay,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: Theme.of(context).cardColor,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: Theme.of(context).dividerColor.withOpacity(0.05)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.02),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: color.withOpacity(0.1),
                shape: BoxShape.circle,
              ),
              child: imagePath != null 
                ? Image.asset(imagePath, width: 28, height: 28)
                : Icon(icon, color: color, size: 28),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: GoogleFonts.poppins(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: Theme.of(context).colorScheme.onSurface,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    subtitle,
                    style: GoogleFonts.inter(
                      fontSize: 12,
                      color: Theme.of(context).colorScheme.onSurface.withOpacity(0.6),
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.arrow_forward_ios_rounded, color: Color(0xFF9CA3AF), size: 16),
          ],
        ),
      ),
    ).animate().fadeIn(delay: delay.ms).moveY(begin: 20, end: 0);
  }
}
