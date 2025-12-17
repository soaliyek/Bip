// Start conversation buttons
document.querySelectorAll('.start-conversation-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const listenerID = this.dataset.userId;
        const username = this.dataset.username;
        
        if (!confirm(`Start a conversation with ${username}?`)) {
            return;
        }
        
        this.disabled = true;
        this.textContent = 'Starting...';
        
        fetch('../../api/startconversation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ listenerID: listenerID })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = `app.php?conversation=${data.conversationID}`;
            } else {
                alert('Failed to start conversation: ' + (data.error || 'Unknown error'));
                this.disabled = false;
                this.textContent = 'Start Conversation';
            }
        })
        .catch(err => {
            console.error('Error starting conversation:', err);
            alert('Failed to start conversation');
            this.disabled = false;
            this.textContent = 'Start Conversation';
        });
    });
});

// Presence ping
setInterval(() => {
    fetch('../../api/ping.php', { method: 'POST' })
        .catch(err => console.error('Ping failed:', err));
}, 30000);

// Auto-refresh every 10 seconds to update online users
setInterval(() => {
    location.reload();
}, 10000);
