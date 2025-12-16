# Bip - Anonymous Peer Support Chat

Bip is an anonymous, web-based peer-support chat application where users can switch between "Talk" and "Listen" modes, have multiple ongoing conversations, and interact in near real-time using PHP, MySQL, and AJAX polling.

## Features

- **Anonymous Authentication**: Username-based system with profile colors instead of photos
- **Dual Modes**: Users can be Talkers (seeking support) or Listeners (offering support)
- **Real-time-like Messaging**: AJAX polling for near-instant message delivery
- **Safety Features**: 
  - Message reporting system
  - User rating and safelist functionality
  - Admin moderation panel
- **Presence Tracking**: See who's online and their current status
- **Multiple Conversations**: Manage several one-on-one chats simultaneously

## Tech Stack

- PHP 7.4+ (no Composer required)
- MySQL 5.7+ / MariaDB 10.3+
- Vanilla JavaScript (no frameworks)
- Pure CSS

## Installation

### 1. Database Setup

```bash
# Create the database and tables
mysql -u your_username -p < sql/schema.sql
```

Or import `sql/schema.sql` through phpMyAdmin.

### 2. Configuration

Edit `env/connect.env` with your database credentials:

```env
DB_HOST=localhost
DB_NAME=Bip
DB_USER=your_username
DB_PASS=your_password
DB_CHARSET=utf8mb4
```

### 3. File Permissions

Ensure the web server can write to the uploads directory:

```bash
chmod 755 public/uploads
```

### 4. Web Server Configuration

#### Apache (.htaccess)

Create `.htaccess` in the root directory:

```apache
RewriteEngine On
RewriteBase /

# Redirect root to index.php
RewriteRule ^$ index.php [L]

# Route everything through appropriate directories
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ $1 [L,QSA]
```

#### Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/bip;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\. {
        deny all;
    }
}
```

## Usage

### Creating an Admin User

1. Register a normal account through the web interface
2. Manually update the database:

```sql
UPDATE User SET isAdmin = 1 WHERE email = 'admin@example.com';
```

### User Flows

**For Talkers:**
1. Click "Talk" button
2. View online listeners
3. Start a conversation with an available listener
4. Chat in real-time

**For Listeners:**
1. Click "Listen" button
2. Wait for talkers to connect
3. Provide supportive conversation

**Safety Features:**
- Hover over any message and click the ⚠ icon to report
- Click on user profiles to rate them
- Positive ratings add users to your safelist
- Admins can review reports and ban users

## Project Structure

```
bip/
├── api/                    # AJAX endpoints
│   ├── ping.php           # Presence tracking
│   ├── updatestatus.php   # Change user mode
│   ├── startconversation.php
│   ├── sendmessage.php
│   ├── getmessages.php
│   ├── flagmessage.php
│   └── rateuser.php
├── authentication/         # Login/Register/Logout
├── config/                # Database configuration
├── env/                   # Environment variables
├── includes/              # Session & helper functions
├── public/                # Public-facing pages
│   ├── css/              # Stylesheets
│   ├── js/               # JavaScript
│   ├── dashboard.php     # Main dashboard
│   ├── chat.php          # Chat interface
│   ├── online-users.php  # Browse online users
│   ├── settings.php      # User settings
│   └── admin.php         # Admin panel
└── sql/                   # Database schema
```

## Development Notes

### AJAX Polling

The app uses AJAX polling every 3 seconds to fetch new messages. This is suitable for university PHP/MySQL hosting where WebSockets aren't available.

### Username Rules

To maintain anonymity:
- Must be 3-50 characters
- Must contain at least one digit
- Cannot contain spaces
- Cannot contain the email's local part

### Profile Colors

Users select from a predefined palette of 12 colors that serve as their "avatar" throughout the app.

## Security Considerations

- All passwords are hashed using PHP's `password_hash()`
- SQL injection prevention via PDO prepared statements
- XSS prevention via output escaping
- CSRF protection recommended for production (add tokens)
- Input validation on both client and server side

## Browser Support

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+

## Contributing

This is an educational project. Feel free to fork and modify for your needs.

## License

This project is created for educational purposes.

## Support

For issues or questions, please create an issue in the repository.

## Acknowledgments

Built as a Web Technologies university project demonstrating PHP, MySQL, and AJAX concepts.
