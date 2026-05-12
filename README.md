<div align="center">
 
<p align="center">
 <img height="200px" src="./public/images/i-Gov_Chatbot.ico" alt="WPRAGBot Logo">
</p>

<h2 align="center">NaguenIA - Naga City Government Digital Intelligent Assistant</h2>

<div align="center">

[![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4+-green.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-red.svg)](LICENSE)

<h4>A next-generation digital assistant powered by Large Language Models (LLM) and Retrieval-Augmented Generation (RAG) techniques, designed for the Naga City Government with multi-provider AI support, vector search, and comprehensive analytics.</h4>

</div>

---

## Project Overview

NaguenIA is a sophisticated WordPress plugin designed as the **Digital Intelligent Assistant for Naga City Government**. It brings advanced AI capabilities powered by **Large Language Models (LLM) and Retrieval-Augmented Generation (RAG)** techniques to deliver intelligent conversational experiences. The system combines multiple AI providers, vector databases, and analytics to create a contextually aware assistant that understands and responds to citizen and staff inquiries with precision and accuracy.

This system is designed to be:

- **Accessible** with an intuitive WordPress interface for government staff and citizens
- **Intelligent** powered by LLM + RAG for accurate, contextual responses
- **Flexible** with support for multiple AI providers (Gemini, OpenRouter, Mistral, OpenAI, etc.)
- **Efficient** with Qdrant vector database for fast document retrieval
- **Transparent** with comprehensive analytics and tracking via Supabase

---

## Core Architecture

### Main Plugin Structure

The NaguenIA system follows a modular architecture pattern for maintainability, reliability, and extensibility:

1. **Main Plugin File** (`wpragbot.php`) — Initializes the plugin and registers activation/deactivation hooks
2. **Core Plugin Class** (`class-wpragbot.php`) — Orchestrates the plugin components using a loader pattern
3. **Loader Class** (`class-wpragbot-loader.php`) — Manages WordPress hooks registration
4. **Admin Interface** (`class-wpragbot-admin.php`) — WordPress admin settings and configuration
5. **Public Interface** (`class-wpragbot-public.php`) — Frontend chat widget and AJAX handlers
6. **API Integration** (`class-wpragbot-api.php`) — Core RAG functionality connecting to multiple AI providers and Qdrant
7. **Embedding** (`class-wpragbot-embedding.php`) — Handles embedding generation using Hugging Face Ollama All-MiniLM L6 v2
8. **Analytics** (`class-wpragbot-analytics-supabase.php`) — Tracks chat interactions and generates reports using Supabase
9. **Activation/Deactivation** (`class-wpragbot-activator.php`, `class-wpragbot-deactivator.php`) — Database setup and cleanup

## Key Features

### 1. RAG (Retrieval-Augmented Generation) Implementation

The core RAG pipeline combines document retrieval with AI generation for accurate, contextual responses:

- **Multi AI Provider Support** — Compatible with Gemini, OpenRouter, Mistral, OpenAI, and other LLM providers for flexibility
- **Vector Embeddings** — Generate embeddings using all-MiniLM-L6-v2 API for semantic understanding
- **Vector Search** — Search relevant documents in Qdrant vector database using cosine similarity
- **Contextual Responses** — Generate responses using selected LLM with retrieved context for accuracy

---

### 2. Admin Functionality

Comprehensive WordPress admin panel for easy configuration and management:

- **Settings Management** — Configure AI provider, API keys, Qdrant URL, API key, and collection name
- **Analytics Dashboard** — View chat statistics and export data using Supabase
- **API Key Security** — Secure handling of API credentials with nonce verification

---

### 3. Public Interface & User Experience

Seamless, intuitive front-end experience for end users:

- **Chat Widget** — Interactive chat interface in the footer with real-time messaging
- **Shortcode Support** — `[wpragbot]` shortcode for embedding chat in posts and pages
- **AJAX Communication** — Seamless communication between frontend and backend
- **Session Management** — Track user sessions and conversation history with localStorage

---

### 4. Analytics & Tracking

Detailed insights into chat performance and user interactions:

- **Interaction Logging** — Track all chat interactions with Supabase database storage
- **Usage Statistics** — View chat volume, response patterns, and user behavior
- **Data Export** — Export analytics data in CSV or JSON formats
- **Data Cleanup** — Automatic cleanup of old data (default 90 days)

---

## Technical Implementation Details

### API Integration Flow

The RAG pipeline follows a structured flow to ensure efficient and accurate responses:

1. **Chat Request** — User sends message via frontend chat interface
2. **Embedding Generation** — all-MiniLM-L6-v2 API creates vector embedding of user message
3. **Document Search** — Qdrant searches for relevant documents using vector similarity
4. **Context Construction** — Relevant documents are compiled into context for the LLM
5. **Reasoning/LLM** — Selected LLM generates response using context and system prompt
6. **Response Delivery** — Response sent back to user via AJAX

---

### Database Schema

NaguenIA uses three main database tables for data management:

- **wpragbot_sessions** — Tracks user sessions with timestamps and session identifiers
- **wpragbot_messages** — Stores conversation history (user and bot messages)
- **wpragbot_analytics** — Stores analytics data for reporting (via Supabase)

---

### Document Processing Pipeline

Documents are processed efficiently to enable fast and accurate retrieval:

- **Content Chunking** — Documents are split into chunks (1500 characters) with 300-character overlap for context
- **Sentence Boundary Detection** — Attempts to break chunks at natural sentence boundaries for readability
- **Vector Embedding** — Each chunk is embedded using all-MiniLM-L6-v2 API via Hugging Face Ollama
- **Qdrant Upload** — Embeddings are uploaded to Qdrant collection with metadata for fast retrieval

---

1. **Setup** — Admin configures AI provider, API keys, Qdrant URL, and collection name in WordPress admin
2. **Knowledge Base** — Documents are already processed and stored in Qdrant Cloud with specified collection name
3. **Usage** — Visitors interact with the chat widget or shortcode
4. **Processing** — Each message triggers the RAG pipeline using selected AI provider and embedding service
5. **Analytics** — Interactions are tracked and can be viewed in admin dashboard using Supabase

---

## Security Considerations

WPRAGBot implements security best practices to protect your data and users:

- **API Key Security** — API keys are stored in WordPress options (not in code)
- **Nonce Verification** — AJAX requests are protected with WordPress nonce verification
- **Input Sanitization** — All user input is sanitized and validated
- **Data Protection** — Secure handling of user data and conversations
- **File Validation** — File type and size validation for document uploads

---

## Advanced Features

WPRAGBot includes several advanced capabilities for production-ready deployment:

- **Multi AI Provider Support** — Compatible with Gemini, OpenRouter, Mistral, OpenAI, and other LLM providers
- **Error Handling** — Comprehensive error handling with logging throughout the system
- **Timeout Management** — Configurable timeouts for API requests
- **Response Formatting** — Uses Markdown formatting for clean, readable responses
- **Session Persistence** — Uses localStorage for session management
- **Performance Optimization** — Intelligent chunking and efficient vector search
- **Supabase Analytics** — Real-time analytics and tracking using Supabase database
- **Hugging Face Ollama Integration** — Embedding generation using Hugging Face Ollama All-MiniLM L6 v2

---

## Installation and Setup

### Prerequisites

Before installing NaguenIA, ensure you have:

1. **WordPress** 5.0 or higher
2. **PHP** 7.4 or higher
3. **API Key** from selected AI provider (Gemini, OpenRouter, Mistral, OpenAI, etc.)
4. **Qdrant Instance** (self-hosted or cloud service)
5. **Supabase Account** for analytics and tracking

---

### Installation Steps

Follow these steps to install and configure WPRAGBot:

**Step I: Install the Plugin**

```
1. Download and install the plugin through WordPress admin or upload manually
2. Navigate to Plugins → Plugins in WordPress admin
3. Activate the NaguenIA plugin
```

**Step II: Configure API Settings**

```
1. Navigate to Settings → NaguenIA
2. Select your AI Provider (Gemini, OpenRouter, Mistral, OpenAI, etc.)
3. Enter your API Key for the selected provider
4. Enter your Qdrant URL
5. Enter Qdrant API Key (optional, if required by your instance)
6. Specify Collection Name (default: knowledge_base)
```

**Step III: Setup Analytics**

```
1. Configure Supabase connection details in the Analytics section
2. Enter your Supabase Project URL
3. Enter your Supabase API Key
4. Test the connection
```

**Step IV: Deploy NaguenIA Assistant**

```
1. Add NaguenIA to your site using the shortcode [naguenia]
2. Or enable the footer widget in Settings → NaguenIA
3. Customize widget appearance and behavior in settings
```

---

## Usage Guide

### Admin Panel Management

Configure and monitor NaguenIA from the WordPress admin:

- **API Settings** — Configure AI provider and API credentials in Settings → NaguenIA
- **Analytics Configuration** — Setup Supabase connection for tracking
- **Widget Customization** — Customize appearance and behavior of the chat widget
- **Analytics Dashboard** — View chat statistics, volume, and user interactions
- **Data Management** — Export data and manage analytics retention

---

### Frontend Usage

Guide citizens and staff to interact with NaguenIA:

- **Chat Widget** — Interactive assistant appears in the footer (if enabled)
- **Shortcode Support** — Use `[naguenia]` shortcode to embed the assistant in any post or page
- **Persistent Sessions** — Chat sessions are tracked using localStorage for continuity
- **Real-time Response** — Receive AI-powered responses in real-time

---

## Troubleshooting Guide

### Common Issues and Solutions

**Issue: API Key Errors**

- **Solution** — Verify that your selected AI provider API key is correct and has necessary permissions

**Issue: Qdrant Connection Issues**

- **Solution** — Check Qdrant URL and API key. Ensure Qdrant instance is running and accessible

**Issue: Database Errors**

- **Solution** — Ensure WordPress has proper database permissions and sufficient storage space

**Issue: CORS Issues**

- **Solution** — If using a self-hosted Qdrant instance, ensure proper CORS configuration

**Issue: Supabase Connection Issues**

- **Solution** — Verify Supabase connection details (Project URL, API Key) are correct

---

### Logging and Debugging

The plugin logs detailed information for troubleshooting:

- **WordPress Error Logs** — Enable debug mode in WordPress to see detailed error messages
- **Plugin Logs** — Check plugin-specific logs in the admin panel
- **Browser Console** — Check browser console for frontend errors (F12 in most browsers)

---

## About NaguenIA

NaguenIA represents a modern approach to government digital services, combining the power of **Large Language Models and Retrieval-Augmented Generation** with the proven reliability of WordPress. Designed specifically for the Naga City Government, NaguenIA provides an intelligent digital assistant that can help citizens and staff find information, submit inquiries, and access government services efficiently.

**Key Highlights:**

- Intuitive interface for both citizens and government staff
- LLM + RAG technology for accurate, context-aware responses
- Support for multiple AI providers for flexibility and cost optimization
- Enterprise-grade security with proper API key handling and data protection
- Comprehensive analytics to track service usage and improve responses
- Seamless WordPress integration for easy deployment and management

---

## Getting Help & Support

Need assistance? Here are your options:

- **Documentation** — Refer to this README for detailed information
- **WordPress Plugins Directory** — Visit the official WordPress plugins page for reviews and community support
- **GitHub Issues** — Report bugs or request features on GitHub (if available)
- **Logging** — Enable debug mode to capture detailed logs for troubleshooting

---

## License

This project is licensed under the MIT License.

---

## Credits & Attribution

**NaguenIA** — Naga City Government Digital Intelligent Assistant powered by LLM + RAG

Built with:

- **WordPress** — Reliable, open-source content management platform
- **Qdrant** — Vector database for efficient semantic search and retrieval
- **Supabase** — Open source backend for analytics and data management
- **Hugging Face** — State-of-the-art machine learning models for embeddings
- **AI Providers** — Gemini, OpenRouter, Mistral, OpenAI, and other LLM services

---

<div align="center">

### Empowering Naga City with Intelligent Government Services

© 2024-2025 NaguenIA. All Rights Reserved.

NaguenIA: A Naga City Government Initiative | Digital Transformation Through AI

For more information, visit: [Project Repository](https://github.com/Devcavi19/naguenia)

</div>
