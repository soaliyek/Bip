# Bip - Anonymous Peer Support Chat

Bip is an anonymous, web-based peer-support chat application where users can switch between "Talk" and "Listen" modes, have multiple ongoing conversations, and interact in near real-time using PHP, MySQL, and AJAX polling.

Find the app here: http://169.239.251.102:341/~soaliye.kindo/uploads/Bip

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
- Input validation on both client and server side

## Acknowledgments

This is My Final Web Technologies Project project demonstrating PHP, MySQL, and AJAX concepts.
