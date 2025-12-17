// ===========================
// GLOBAL VARIABLES
// ===========================

let lastMessageID = 0;
let pollingInterval = null;

// ===========================
// INITIALIZATION
// ===========================

document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    // Initialize message area if conversation is selected
    if (conversationID) {
        initializeMessages();
        startPolling();
    }
    
    // Initialize textarea auto-grow
    initializeTextarea();
    
    // Initialize event listeners
    initializeEventListeners();
    
    // Start presence ping
    startPresencePing();
}

// ===========================
// CONVERSATION SELECTION
// ===========================

function initializeEventListeners() {
    // Conversation click handlers
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.addEventListener('click', function() {
            const convID = this.dataset.conversationId;
            window.location.href = `app.php?conversation=${convID}`;
        });
    });
    
    // Navigation buttons
    const bipersBtn = document.getElementById('bipersBtn');
    if (bipersBtn) {
        bipersBtn.addEventListener('click', () => {
            window.location.href = 'online-users.php';
        });
    }
    
    const talkBtn = document.getElementById('talkBtn');
    if (talkBtn) {
        talkBtn.addEventListener('click', () => {
            setMode('LOOKING_TO_TALK').then(() => {
                window.location.href = 'online-users.php';
            });
        });
    }
    
    const listenBtn = document.getElementById('listenBtn');
    if (listenBtn) {
        listenBtn.addEventListener('click', () => {
            setMode('LISTENER_AVAILABLE').then(() => {
                alert('You are now in Listen mode. Other users can start conversations with you.');
            });
        });
    }
    
    // Conversation search
    const searchInput = document.getElementById('conversationSearch');
    if (searchInput && !searchInput.disabled) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const items = document.querySelectorAll('.conversation-item');
            
            items.forEach(item => {
                const username = item.querySelector('.conv-username').textContent.toLowerCase();
                if (username.includes(query)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
    
    // Message form
    const messageForm = document.getElementById('messageForm');
    if (messageForm) {
        messageForm.addEventListener('submit', handleMessageSubmit);
    }
    
    // Report buttons (will be added dynamically)
    
    // Modals
    initializeModals();
}

// ===========================
// USER STATUS MANAGEMENT
// ===========================

function setMode(mode) {
    return fetch('../../api/updatestatus.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mode: mode })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.error || 'Failed to update status');
        }
        return data;
    })
    .catch(err => {
        console.error('Error updating status:', err);
        alert('Failed to update status');
        throw err;
    });
}

// ===========================
// MESSAGES FUNCTIONALITY
// ===========================

function initializeMessages() {
    // Get last message ID from existing messages
    const messages = document.querySelectorAll('.message[data-message-id]');
    if (messages.length > 0) {
        const lastMsg = messages[messages.length - 1];
        lastMessageID = parseInt(lastMsg.dataset.messageId);
    }
    
    // Scroll to bottom
    scrollToBottom();
    
    // Add report button handlers to existing messages
    document.querySelectorAll('.message-report-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const messageID = this.dataset.messageId;
            openReportModal(messageID);
        });
    });
}

function initializeTextarea() {
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            const newHeight = Math.min(this.scrollHeight, 150); // 150px max height
            this.style.height = newHeight + 'px';
        });
        
        // Allow Shift+Enter for new line, Enter to send
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('messageForm').dispatchEvent(new Event('submit'));
            }
        });
    }
}

function handleMessageSubmit(e) {
    e.preventDefault();
    
    const messageInput = document.getElementById('messageInput');
    const content = messageInput.value.trim();
    
    if (!content) return;
    
    // Disable input while sending
    messageInput.disabled = true;
    
    fetch('../../api/sendmessage.php', {
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
            messageInput.value = '';
            messageInput.style.height = 'auto';
            appendMessage(data.message);
            scrollToBottom();
        } else {
            console.error('Send message error:', data.error);
            alert('Failed to send message: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Error sending message:', err);
        alert('Failed to send message. Please check your connection.');
    })
    .finally(() => {
        messageInput.disabled = false;
        messageInput.focus();
    });
}

function startPolling() {
    // Clear any existing interval
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
    
    pollingInterval = setInterval(() => {
        if (!conversationID) return;
        
        fetch(`../../api/getmessages.php?conversationID=${conversationID}&lastMessageID=${lastMessageID}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.messages && data.messages.length > 0) {
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
        const isOwn = message.senderUserID == currentUserID;
        messageDiv.className = `message ${isOwn ? 'message-own' : 'message-other'}`;
        messageDiv.dataset.messageId = message.messageID;
        
        // Build message HTML
        let messageHTML = `
            <div class="message-avatar">
                <div class="message-avatar-circle" style="background-color: ${message.senderColor}"></div>
            </div>
            <div class="message-content">
                <div class="message-bubble ${message.isFlagged ? 'message-flagged' : ''}">
                    ${escapeHtml(message.content).replace(/\n/g, '<br>')}
                </div>
                <div class="message-time">${formatTime(message.createdAt)}</div>
            </div>
        `;
        
        // Only add report button for other user's messages
        if (!isOwn) {
            messageHTML += `<button class="message-report-btn" data-message-id="${message.messageID}" title="Report this message">⚠</button>`;
        }
        
        messageDiv.innerHTML = messageHTML;
        
        // Add report functionality only if button exists
        if (!isOwn) {
            const reportBtn = messageDiv.querySelector('.message-report-btn');
            if (reportBtn) {
                reportBtn.addEventListener('click', function() {
                    openReportModal(message.messageID);
                });
            }
        }
    }
    
    messagesArea.appendChild(messageDiv);
}

function updateInterlocutorStatus(status) {
    const statusEl = document.getElementById('interlocutorStatus');
    if (statusEl) {
        const isOnline = status.isOnline && isUserOnline(status.lastSeenAt);
        const text = getStatusText(status.mode, isOnline);
        statusEl.textContent = text;
    }
}

function scrollToBottom() {
    const messagesArea = document.getElementById('messagesArea');
    if (messagesArea) {
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }
}

// ===========================
// MODAL FUNCTIONALITY
// ===========================

function initializeModals() {
    // Report Modal
    const reportModal = document.getElementById('reportModal');
    const reportForm = document.getElementById('reportForm');
    const reportCloseBtn = reportModal ? reportModal.querySelector('.modal-close') : null;
    
    if (reportCloseBtn) {
        reportCloseBtn.addEventListener('click', () => {
            reportModal.style.display = 'none';
        });
    }
    
    if (reportForm) {
        reportForm.addEventListener('submit', handleReportSubmit);
    }
    
    // Profile Modal
    const profileModal = document.getElementById('profileModal');
    const profileCloseBtn = profileModal ? profileModal.querySelector('.modal-close') : null;
    
    if (profileCloseBtn) {
        profileCloseBtn.addEventListener('click', () => {
            profileModal.style.display = 'none';
        });
    }
    
    const profileTrigger = document.getElementById('interlocutorProfile');
    if (profileTrigger && profileModal) {
        profileTrigger.addEventListener('click', () => {
            profileModal.style.display = 'block';
        });
    }
    
    // Star Rating
    initializeStarRating();
    
    // Flag Buttons
    initializeFlagButtons();
    
    // Close modals on outside click
    window.addEventListener('click', function(e) {
        if (e.target === reportModal) {
            reportModal.style.display = 'none';
        }
        if (e.target === profileModal) {
            profileModal.style.display = 'none';
        }
    });
}

function openReportModal(messageID) {
    const reportModal = document.getElementById('reportModal');
    document.getElementById('reportMessageID').value = messageID;
    reportModal.style.display = 'block';
}

function handleReportSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = {
        messageID: formData.get('messageID'),
        flagType: formData.get('flagType'),
        reason: formData.get('reason')
    };
    
    fetch('../../api/flagmessage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Message reported successfully');
            document.getElementById('reportModal').style.display = 'none';
            e.target.reset();
        } else {
            alert('Failed to report: ' + (result.error || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Error reporting message:', err);
        alert('Failed to report message');
    });
}

function initializeStarRating() {
    const stars = document.querySelectorAll('#starRating .star');
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.dataset.value);
            
            // Update UI
            stars.forEach((s, index) => {
                s.textContent = index < rating ? '★' : '☆';
            });
            
            // Send rating
            if (interlocutorID) {
                fetch('../../api/rateuser.php', {
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
            }
        });
    });
}

function initializeFlagButtons() {
    document.querySelectorAll('.btn-flag').forEach(btn => {
        btn.addEventListener('click', function() {
            const flagType = this.dataset.flag;
            
            if (!interlocutorID) return;
            
            fetch('../../api/rateuser.php', {
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
}

// ===========================
// PRESENCE MANAGEMENT
// ===========================

function startPresencePing() {
    // Ping immediately
    sendPresencePing();
    
    // Then ping every 30 seconds
    setInterval(sendPresencePing, 30000);
}

function sendPresencePing() {
    fetch('../../api/ping.php', { method: 'POST' })
        .catch(err => console.error('Ping failed:', err));
}

// ===========================
// UTILITY FUNCTIONS
// ===========================

function isUserOnline(lastSeenAt) {
    if (!lastSeenAt) return false;
    const diff = (Date.now() - new Date(lastSeenAt).getTime()) / 1000;
    return diff <= 180; // 3 minutes
}

function getStatusColor(mode, isOnline) {
    if (!isOnline) return '#EF4444';
    switch (mode) {
        case 'LISTENER_AVAILABLE': return '#10B981';
        case 'LOOKING_TO_TALK': return '#FCD34D';
        case 'IN_CONVERSATION': return '#10B981';
        default: return '#FCD34D';
    }
}

function getStatusText(mode, isOnline) {
    if (!isOnline) return 'Offline';
    switch (mode) {
        case 'LISTENER_AVAILABLE': return 'Listening';
        case 'LOOKING_TO_TALK': return 'Looking to talk';
        case 'IN_CONVERSATION': return 'Online';
        case 'IDLE': return 'Online';
        default: return 'Online';
    }
}

function formatTime(datetime) {
    const date = new Date(datetime);
    return date.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ===========================
// CLEANUP
// ===========================

window.addEventListener('beforeunload', function() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
});

// Page Visibility API - pause polling when tab not visible
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    } else {
        if (conversationID && !pollingInterval) {
            startPolling();
        }
    }
});
