// Presence ping every 30 seconds
setInterval(() => {
    fetch('/api/ping.php', { method: 'POST' })
        .catch(err => console.error('Ping failed:', err));
}, 30000);

// Listen button
document.getElementById('listenBtn')?.addEventListener('click', function() {
    setMode('LISTENER_AVAILABLE');
});

// Talk button
document.getElementById('talkBtn')?.addEventListener('click', function() {
    window.location.href = '/public/online-users.php';
});

// See Online Users button
document.getElementById('seeOnlineBtn')?.addEventListener('click', function() {
    window.location.href = '/public/online-users.php';
});

// Set user mode
function setMode(mode) {
    fetch('/api/updatestatus.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mode: mode })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (mode === 'LISTENER_AVAILABLE') {
                alert('You are now in Listen mode. Other users can start conversations with you.');
            }
            // Refresh to see online users or update UI
            if (mode === 'LOOKING_TO_TALK') {
                window.location.href = '/public/online-users.php';
            }
        } else {
            alert('Failed to update status: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Error updating status:', err);
        alert('Failed to update status');
    });
}

// Conversation search
const searchInput = document.getElementById('conversationSearch');
if (searchInput && !searchInput.disabled) {
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        const conversations = document.querySelectorAll('.conversation-item');
        
        conversations.forEach(conv => {
            const username = conv.querySelector('.conv-username').textContent.toLowerCase();
            const preview = conv.querySelector('.conv-preview').textContent.toLowerCase();
            
            if (username.includes(query) || preview.includes(query)) {
                conv.style.display = '';
            } else {
                conv.style.display = 'none';
            }
        });
    });
}

// Click on conversation
document.querySelectorAll('.conversation-item').forEach(item => {
    item.addEventListener('click', function() {
        const conversationID = this.dataset.conversationId;
        window.location.href = `/public/chat.php?id=${conversationID}`;
    });
});
