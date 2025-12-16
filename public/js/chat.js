let lastMessageID = 0;
let pollingInterval;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Get last message ID from existing messages
    const messages = document.querySelectorAll('.message[data-message-id]');
    if (messages.length > 0) {
        const lastMsg = messages[messages.length - 1];
        lastMessageID = parseInt(lastMsg.dataset.messageId);
    }
    
    // Scroll to bottom
    scrollToBottom();
    
    // Start polling for new messages
    startPolling();
    
    // Auto-resize textarea
    const messageInput = document.getElementById('messageInput');
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
});

// Send message
document.getElementById('messageForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const content = document.getElementById('messageInput').value.trim();
    if (!content) return;
    
    fetch('/api/sendmessage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            conversationID: conversationID,
            content: content
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('messageInput').value = '';
            document.getElementById('messageInput').style.height = 'auto';
            appendMessage(data.message);
            scrollToBottom();
        } else {
            alert('Failed to send message: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Error sending message:', err);
        alert('Failed to send message');
    });
});

// Poll for new messages
function startPolling() {
    pollingInterval = setInterval(() => {
        fetch(`/api/getmessages.php?conversationID=${conversationID}&lastMessageID=${lastMessageID}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.messages.length > 0) {
                    data.messages.forEach(msg => appendMessage(msg));
                    scrollToBottom();
                }
                
                // Update interlocutor status
                if (data.interlocutorStatus) {
                    updateInterlocutorStatus(data.interlocutorStatus);
                }
            })
            .catch(err => console.error('Polling error:', err));
    }, 3000); // Poll every 3 seconds
}

// Append message to chat
function appendMessage(message) {
    if (message.messageID) {
        lastMessageID = Math.max(lastMessageID, parseInt(message.messageID));
    }
    
    const messagesArea = document.getElementById('messagesArea');
    const messageDiv = document.createElement('div');
    
    if (message.isSystem == 1) {
        messageDiv.className = 'message-system';
        messageDiv.textContent = message.content;
    } else {
        const isOwn = message.senderUserID == <?= $user['userID'] ?>;
        messageDiv.className = `message ${isOwn ? 'message-own' : 'message-other'}`;
        messageDiv.dataset.messageId = message.messageID;
        
        messageDiv.innerHTML = `
            <div class="message-avatar">
                <div class="profile-circle-small" style="background-color: ${message.senderColor}"></div>
            </div>
            <div class="message-content">
                <div class="message-bubble ${message.isFlagged ? 'message-flagged' : ''}">
                    ${escapeHtml(message.content).replace(/\n/g, '<br>')}
                </div>
                <div class="message-time">${formatTime(message.createdAt)}</div>
            </div>
            <button class="message-report-btn" title="Report this message">⚠</button>
        `;
        
        // Add report functionality
        const reportBtn = messageDiv.querySelector('.message-report-btn');
        reportBtn.addEventListener('click', function() {
            openReportModal(message.messageID);
        });
    }
    
    messagesArea.appendChild(messageDiv);
}

// Update interlocutor status
function updateInterlocutorStatus(status) {
    const statusEl = document.getElementById('interlocutorStatus');
    if (statusEl) {
        const isOnline = status.isOnline && isUserOnline(status.lastSeenAt);
        const color = getStatusColor(status.mode, isOnline);
        const text = getStatusText(status.mode, isOnline);
        
        statusEl.innerHTML = `
            <span class="status-dot-small" style="background-color: ${color}"></span>
            ${text}
        `;
    }
}

// Helper: Check if user is online
function isUserOnline(lastSeenAt) {
    const diff = (Date.now() - new Date(lastSeenAt).getTime()) / 1000;
    return diff <= 180; // 3 minutes
}

// Helper: Get status color
function getStatusColor(mode, isOnline) {
    if (!isOnline) return '#6C757D';
    switch (mode) {
        case 'LISTENER_AVAILABLE': return '#52B788';
        case 'LOOKING_TO_TALK': return '#4ECDC4';
        case 'IN_CONVERSATION': return '#FFA07A';
        default: return '#85C1E2';
    }
}

// Helper: Get status text
function getStatusText(mode, isOnline) {
    if (!isOnline) return 'Offline';
    switch (mode) {
        case 'LISTENER_AVAILABLE': return 'Listening';
        case 'LOOKING_TO_TALK': return 'Looking to talk';
        case 'IN_CONVERSATION': return 'In conversation';
        default: return 'Online';
    }
}

// Scroll to bottom
function scrollToBottom() {
    const messagesArea = document.getElementById('messagesArea');
    messagesArea.scrollTop = messagesArea.scrollHeight;
}

// Format time
function formatTime(datetime) {
    const date = new Date(datetime);
    return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Report Modal
const reportModal = document.getElementById('reportModal');
const reportForm = document.getElementById('reportForm');
const reportCloseBtn = reportModal.querySelector('.modal-close');

function openReportModal(messageID) {
    document.getElementById('reportMessageID').value = messageID;
    reportModal.style.display = 'block';
}

reportCloseBtn.addEventListener('click', function() {
    reportModal.style.display = 'none';
});

reportForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {
        messageID: formData.get('messageID'),
        flagType: formData.get('flagType'),
        reason: formData.get('reason')
    };
    
    fetch('/api/flagmessage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Message reported successfully');
            reportModal.style.display = 'none';
            reportForm.reset();
        } else {
            alert('Failed to report: ' + (result.error || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Error reporting message:', err);
        alert('Failed to report message');
    });
});

// Profile Modal
const profileModal = document.getElementById('profileModal');
const profileCloseBtn = profileModal.querySelector('.modal-close');

document.getElementById('interlocutorProfile').addEventListener('click', function() {
    profileModal.style.display = 'block';
});

profileCloseBtn.addEventListener('click', function() {
    profileModal.style.display = 'none';
});

// Star Rating
const stars = document.querySelectorAll('#starRating .star');
stars.forEach(star => {
    star.addEventListener('click', function() {
        const rating = parseInt(this.dataset.value);
        
        // Update UI
        stars.forEach((s, index) => {
            s.textContent = index < rating ? '★' : '☆';
        });
        
        // Send rating
        fetch('/api/rateuser.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                targetUserID: interlocutorID,
                ratingValue: rating
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Rating submitted');
            }
        })
        .catch(err => console.error('Error submitting rating:', err));
    });
});

// Flag User Buttons
document.querySelectorAll('.btn-flag').forEach(btn => {
    btn.addEventListener('click', function() {
        const flagType = this.dataset.flag;
        
        fetch('/api/rateuser.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                targetUserID: interlocutorID,
                flagType: flagType
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`User flagged as ${flagType}`);
                this.disabled = true;
                this.textContent += ' ✓';
            } else {
                alert('Failed to flag user: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('Error flagging user:', err);
            alert('Failed to flag user');
        });
    });
});

// Close modals on outside click
window.addEventListener('click', function(e) {
    if (e.target === reportModal) {
        reportModal.style.display = 'none';
    }
    if (e.target === profileModal) {
        profileModal.style.display = 'none';
    }
});

// Presence ping
setInterval(() => {
    fetch('/api/ping.php', { method: 'POST' })
        .catch(err => console.error('Ping failed:', err));
}, 30000);

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    clearInterval(pollingInterval);
});
