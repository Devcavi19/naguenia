# WordPress RAG Plugin Documentation

## Overview

This is an AI-powered chatbot plugin for WordPress that implements a **Retrieval-Augmented Generation (RAG)** system using:

- **Multi AI Provider Support** for natural language processing and response generation (Gemini, OpenRouter, Mistral, OpenAI, etc.)
- **Qdrant Vector Database** for document storage and similarity search
- **WordPress** as the platform for integration
- **Supabase** for analytics and tracking

## Core Architecture

### Main Plugin Structure

1. **Main Plugin File** (`wpragbot.php`) - Initializes the plugin and registers activation/deactivation hooks
2. **Core Plugin Class** (`class-wpragbot.php`) - Orchestrates the plugin components using a loader pattern
3. **Loader Class** (`class-wpragbot-loader.php`) - Manages WordPress hooks registration
4. **Admin Interface** (`class-wpragbot-admin.php`) - WordPress admin settings and configuration
5. **Public Interface** (`class-wpragbot-public.php`) - Frontend chat widget and AJAX handlers
6. **API Integration** (`class-wpragbot-api.php`) - Core RAG functionality connecting to multiple AI providers and Qdrant
7. **Analytics** (`class-wpragbot-analytics.php`) - Tracks chat interactions and generates reports using Supabase
8. **Activation/Deactivation** (`class-wpragbot-activator.php`, `class-wpragbot-deactivator.php`) - Database setup and cleanup

## Key Features

### 1. RAG Implementation

- **Multi AI Provider Support**: Compatible with Gemini, OpenRouter, Mistral, OpenAI, and other LLM providers
- **Vector Embeddings**: Generate embeddings using all-MiniLM-L6-v2 API for semantic understanding
- **Vector Search**: Search relevant documents in Qdrant vector database using cosine similarity
- **Contextual Responses**: Generate responses using selected LLM with retrieved context

### 2. Admin Functionality

- **Settings Management**: Configure AI provider, API keys, Qdrant URL, API key, and collection name
- **Analytics Dashboard**: View chat statistics and export data using Supabase
- **API Key Security**: Secure handling of API credentials with nonce verification

### 3. Public Interface

- **Chat Widget**: Interactive chat interface in the footer
- **Shortcode Support**: `[wpragbot]` shortcode for embedding chat in posts/pages
- **AJAX Communication**: Seamless communication between frontend and backend
- **Session Management**: Track user sessions and conversation history with localStorage

### 4. Analytics & Tracking

- **Interaction Logging**: Track all chat interactions with Supabase database storage
- **Usage Statistics**: View chat volume, response patterns, and user behavior
- **Data Export**: Export analytics data in CSV or JSON formats
- **Data Cleanup**: Automatic cleanup of old data (default 90 days)

## Technical Implementation Details

### API Integration Flow

1. **Chat Request**: User sends message via frontend
2. **Embedding Generation**: all-MiniLM-L6-v2 API creates vector embedding of user message
3. **Document Search**: Qdrant searches for relevant documents using vector similarity
4. **Context Construction**: Relevant documents are compiled into context
5. **Reasoning/LLM**: Selected LLM generates response using context and system prompt
6. **Response Delivery**: Response sent back to user via AJAX

### Database Schema

- **wpragbot_sessions**: Track user sessions with timestamps
- **wpragbot_messages**: Store conversation history (user/bot messages)
- **wpragbot_analytics**: Store analytics data for reporting (Supabase)

### Document Processing Pipeline

- **Content Chunking**: Documents are split into chunks (1500 characters) with 300-character overlap
- **Sentence Boundary Detection**: Attempts to break chunks at natural sentence boundaries
- **Vector Embedding**: Each chunk is embedded using all-MiniLM-L6-v2 API
- **Qdrant Upload**: Embeddings are uploaded to Qdrant collection with metadata

## Plugin Workflow

1. **Setup**: Admin configures AI provider, API keys, Qdrant URL, and collection name in WordPress admin
2. **Knowledge Base**: Documents are already processed and stored in Qdrant Cloud with specified collection name
3. **Usage**: Visitors interact with the chat widget or shortcode
4. **Processing**: Each message triggers the RAG pipeline using selected AI provider
5. **Analytics**: Interactions are tracked and can be viewed in admin dashboard using Supabase

## Security Considerations

- API keys are stored in WordPress options (not in code)
- Nonce verification for AJAX requests
- Input sanitization and validation
- Secure handling of user data
- File type and size validation for document uploads

## Key Technical Features

- **Multi AI Provider Support**: Compatible with Gemini, OpenRouter, Mistral, OpenAI, and other LLM providers
- **Error Handling**: Comprehensive error handling with logging throughout the system
- **Timeout Management**: Configurable timeouts for API requests
- **Response Formatting**: Uses Markdown formatting for clean, readable responses
- **Session Persistence**: Uses localStorage for session management
- **Performance Optimization**: Chunking and efficient vector search
- **Supabase Analytics**: Analytics and tracking using Supabase database

## Installation and Setup

### Prerequisites

1. WordPress 5.0 or higher
2. PHP 7.4 or higher
3. API key from selected AI provider (Gemini, OpenRouter, Mistral, OpenAI, etc.)
4. Qdrant instance (self-hosted or cloud service)
5. Supabase account for analytics

### Installation Steps

1. Download and install the plugin through WordPress admin or upload manually
2. Activate the plugin
3. Navigate to Settings → WPRAGBot
4. Configure your API settings:
   - AI Provider (Gemini, OpenRouter, Mistral, OpenAI, etc.)
   - API Key for selected provider
   - Qdrant URL
   - Qdrant API Key (optional)
   - Collection Name (default: knowledge_base)
5. Configure Supabase connection details for analytics
6. Add the chat widget to your site using the shortcode `[wpragbot]` or enable the footer widget

## Usage

### Admin Panel

- Configure API settings in Settings → WPRAGBot
- Configure Supabase analytics connection details
- View analytics and export data

### Frontend

- Chat widget appears in the footer (if enabled)
- Use shortcode `[wpragbot]` to embed chat in posts/pages
- Chat sessions are tracked using localStorage

## Troubleshooting

### Common Issues

1. **API Key Errors**: Verify that your selected AI provider API key is correct
2. **Qdrant Connection Issues**: Check Qdrant URL and API key (if required)
3. **Database Issues**: Ensure WordPress has proper database permissions
4. **CORS Issues**: If using a self-hosted Qdrant instance, ensure proper CORS configuration
5. **Supabase Connection Issues**: Verify Supabase connection details for analytics

### Logging

The plugin logs detailed information to WordPress error logs. Enable debug mode in WordPress to see detailed error messages.
