-- TicketTime Seed Data

-- Default admin user: admin@tickettime.local / Admin1234!
INSERT INTO admins (name, email, password_hash, role, status) VALUES
('System Admin', 'admin@tickettime.local', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', 'admin', 'active'),
('Box Office', 'boxoffice@tickettime.local', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', 'box_office', 'active'),
('Gate Scanner', 'scanner@tickettime.local', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', 'scanner', 'active');

-- Demo Event 1
INSERT INTO events (event_name, event_slug, event_description, event_location, event_start, event_end, sale_start, sale_end, status) VALUES
('Summer Music Festival 2026', 'summer-music-festival-2026',
 'Join us for an amazing outdoor music festival featuring top artists across three stages. Food vendors, craft beer, and family-friendly fun all day long.',
 'Riverside Amphitheater, 100 River Road, Springfield',
 '2026-07-15 16:00:00', '2026-07-15 23:00:00',
 '2026-04-01 00:00:00', '2026-07-15 14:00:00',
 'active');

-- Demo Event 2
INSERT INTO events (event_name, event_slug, event_description, event_location, event_start, event_end, sale_start, sale_end, status) VALUES
('Tech Conference 2026', 'tech-conference-2026',
 'A full-day conference covering AI, cloud computing, and the future of software development. Keynotes, workshops, and networking opportunities.',
 'Downtown Convention Center, 500 Main St, Springfield',
 '2026-09-20 08:00:00', '2026-09-20 18:00:00',
 '2026-04-15 00:00:00', '2026-09-19 23:59:00',
 'active');

-- Ticket types for Summer Music Festival (event_id = 1)
INSERT INTO ticket_types (event_id, ticket_name, ticket_description, price, service_fee, quantity_available, max_per_order, status, sort_order) VALUES
(1, 'General Admission', 'Access to all general admission areas and main stage.', 45.00, 5.00, 500, 10, 'active', 1),
(1, 'VIP Access', 'Premium viewing area, dedicated bar, and backstage meet & greet pass.', 125.00, 10.00, 50, 4, 'active', 2),
(1, 'Group Pack (4 tickets)', 'Four general admission tickets at a discounted rate.', 160.00, 15.00, 50, 2, 'active', 3);

-- Ticket types for Tech Conference (event_id = 2)
INSERT INTO ticket_types (event_id, ticket_name, ticket_description, price, service_fee, quantity_available, max_per_order, status, sort_order) VALUES
(2, 'Standard Pass', 'Full conference access including all keynotes and breakout sessions.', 199.00, 12.00, 300, 5, 'active', 1),
(2, 'Workshop Add-on', 'Hands-on afternoon workshop session (must also have Standard Pass).', 79.00, 5.00, 60, 2, 'active', 2),
(2, 'Virtual Ticket', 'Live stream access to all main stage sessions.', 49.00, 3.00, 1000, 10, 'active', 3);

-- Default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'TicketTime'),
('site_url', 'http://localhost'),
('support_email', 'support@tickettime.local'),
('support_phone', '(555) 000-0000'),
('tax_rate', '0.00'),
('currency', 'USD'),
('currency_symbol', '$'),
('stripe_enabled', '1'),
('email_from_name', 'TicketTime'),
('email_from_address', 'noreply@tickettime.local'),
('willcall_enabled', '1'),
('scan_auto_reset_ms', '3000'),
('max_scan_rate_per_minute', '60');
