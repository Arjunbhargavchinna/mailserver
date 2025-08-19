# MailFlow - PHP Email Management System

A comprehensive email management system built with PHP, featuring a modern admin interface and full email functionality.

## Features

- **User Authentication & Authorization**
  - Secure login/logout system
  - Role-based access control (Administrator, Manager, User)
  - Failed login attempt protection
  - Session management

- **Email Management**
  - Send, receive, and organize emails
  - Draft saving functionality
  - Email attachments support
  - Star/unstar emails
  - Search functionality
  - Folder organization (Inbox, Sent, Drafts, Spam, Trash)
  - Reply and forward functionality with attachments
  - Auto-save drafts

- **Admin Dashboard**
  - System status monitoring
  - User management
  - Audit logging
  - Storage usage tracking
  - Mail queue monitoring
  - Bulk email operations
  - Department/unit-wise management
  - Mail server configuration
  - SMTP integration with popular providers

- **Modern UI/UX**
  - Responsive design with Tailwind CSS
  - Material Design icons
  - Smooth animations and transitions
  - Mobile-friendly interface

- **Advanced Features**
  - Bulk email sending to departments/units
  - Email templates for common messages
  - Mail server testing and configuration
  - Department-based user organization
  - Enhanced attachment handling

## Installation

1. **Database Setup**
   ```bash
   mysql -u root -p < database.sql
   ```

2. **Configuration**
   - Update database credentials in `includes/config.php`
   - Configure SMTP settings for email sending
   - Set appropriate file permissions

3. **Web Server**
   - Ensure PHP 7.4+ is installed
   - Configure your web server to serve the application
   - Enable required PHP extensions (PDO, mysqli)

## Default Login

- **Email:** admin@mailflow.com
- **Password:** admin123

## File Structure

```
mailflow/
│   ├── bulk-operations.php # Bulk email operations
│   ├── mail-server.php     # Mail server configuration
│   ├── departments.php     # Department management
├── includes/
│   ├── config.php          # Database and app configuration
│   ├── auth.php            # Authentication functions
│   └── functions.php       # Core application functions
├── ajax/
│   ├── toggle_star.php     # AJAX endpoint for starring emails
│   └── delete_email.php    # AJAX endpoint for deleting emails
│   ├── upload_attachment.php # File upload handler
├── index.php               # Main dashboard
├── login.php               # Login page
├── logout.php              # Logout handler
├── compose.php             # Email composition
├── email.php               # Email viewing
├── database.sql            # Database schema
└── README.md               # This file
```

## Database Schema

The application uses the following main tables:

- `users` - User accounts and authentication
- `emails` - Email messages and metadata
- `email_attachments` - File attachments
- `email_labels` - Custom labels/tags
- `email_queue` - Email sending queue
- `audit_logs` - System activity logging
- `notifications` - User notifications
- `system_settings` - Application configuration
- `departments` - Department/unit organization

## Security Features

- Password hashing with PHP's `password_hash()`
- SQL injection prevention with prepared statements
- XSS protection with input sanitization
- CSRF protection for forms
- Session security with timeout
- Failed login attempt limiting
- Audit logging for security events

## API Endpoints

The application includes AJAX endpoints for dynamic functionality:

- `POST /ajax/toggle_star.php` - Toggle email star status
- `POST /ajax/delete_email.php` - Delete/move email to trash
- `POST /ajax/auto_save.php` - Auto-save draft emails
- `POST /ajax/upload_attachment.php` - Upload email attachments

## Customization

The application uses a modular structure that makes it easy to:

- Add new email folders/categories
- Implement custom email filters
- Extend the admin dashboard
- Add new user roles and permissions
- Integrate with external email services
- Configure SMTP servers (Gmail, Outlook, custom)
- Set up department-based email distribution

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser with JavaScript enabled

## Mail Server Configuration

The system supports various SMTP configurations:

### Gmail SMTP
- Host: smtp.gmail.com
- Port: 587
- Encryption: TLS
- Note: Use App Password for authentication

### Outlook SMTP
- Host: smtp-mail.outlook.com
- Port: 587
- Encryption: TLS

## License

This project is open source and available under the MIT License.