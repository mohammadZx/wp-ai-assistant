# WP AI Assistant

A powerful, AI-driven content generation and management assistant for WordPress that brings the capabilities of modern AI models directly into your WordPress admin panel. Create, edit, and manage your content with natural language commands, powered by OpenAI, Google Gemini, or custom AI providers.

## 🚀 Features

### 🤖 Intelligent Chat Interface
- **Natural Language Interaction**: Chat with AI to manage your WordPress site using plain English
- **Function Calling**: AI can perform real actions like creating posts, editing content, managing categories, and more
- **Context-Aware**: The AI understands your current post selection and site structure
- **Chat History**: Save and manage multiple chat sessions
- **Export/Import**: Export your conversations for backup or sharing

### ✍️ Content Generation
- **Smart Content Creation**: Generate high-quality posts and pages from simple prompts
- **Multi-Editor Support**: Works seamlessly with Gutenberg, Elementor, and Classic editors
- **Intent Detection**: Automatically detects what you want to create and optimizes the output
- **Topic Management**: Organize and reuse content templates and guidelines
- **Customizable Settings**: Fine-tune temperature, tokens, and other AI parameters

### 🌐 Web Crawler
- **URL Analysis**: Analyze any webpage and extract key information
- **Site Crawling**: Crawl entire websites to understand structure and content
- **Content Suggestions**: Get AI-powered suggestions based on crawled content
- **SEO Insights**: Analyze content for SEO optimization opportunities

### 🔧 Advanced Capabilities
- **Multi-Provider Support**: Choose from OpenAI (GPT-3.5, GPT-4, GPT-4 Turbo, GPT-4o), Google Gemini (Pro, Flash, Vision), or use custom API endpoints
- **Security Features**: Built-in security checks and sanitization for all AI-generated content
- **Meta Box Integration**: Quick AI assistance directly in the post editor
- **Audit Tools**: Review and audit AI-generated content before publishing
- **Flexible Configuration**: Customize API settings, models, and behavior to fit your needs

## 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- An API key from one of the supported AI providers (OpenAI, Google Gemini, or custom endpoint)

## 🛠️ Installation

1. Download or clone this repository into your WordPress plugins directory:
   ```bash
   cd wp-content/plugins
   git clone [repository-url] wpai-assistant
   ```

2. Activate the plugin through the WordPress admin panel:
   - Navigate to **Plugins** → **Installed Plugins**
   - Find **WP AI Assistant** and click **Activate**

3. Configure your API settings:
   - Go to **WP AI Assistant** → **Settings**
   - Enter your API key and select your preferred provider
   - Choose your default model and adjust settings as needed

## 📖 Usage

### Getting Started

1. **Access the Chat Interface**: Navigate to **WP AI Assistant** → **Chat** in your WordPress admin
2. **Start a Conversation**: Type your request in natural language, such as:
   - "Create a blog post about WordPress security tips"
   - "Edit post #123 to add a conclusion paragraph"
   - "Generate 5 topic ideas for a tech blog"

3. **Use Function Calling**: The AI can automatically:
   - Create new posts and pages
   - Edit existing content
   - Manage categories and tags
   - Set featured images
   - Update meta information
   - And much more!

### Content Generation

1. Go to **WP AI Assistant** → **Content Generator**
2. Enter your prompt or topic
3. Select post type, editor, and other options
4. Click **Generate** and review the result
5. Apply directly to your WordPress editor or make manual adjustments

### Web Crawler

1. Navigate to **WP AI Assistant** → **Crawler**
2. Enter a URL to analyze or crawl an entire site
3. Review the extracted content and AI analysis
4. Use the suggestions to create new content

## 🎯 Use Cases

- **Content Creators**: Quickly generate blog posts, articles, and pages
- **Site Managers**: Bulk edit content, manage categories, and organize site structure
- **Developers**: Automate content creation workflows and integrate AI into custom solutions
- **SEO Specialists**: Generate SEO-optimized content and analyze competitor sites
- **Content Teams**: Collaborate using shared chat sessions and topic templates

## 🔒 Security

WP AI Assistant includes built-in security features:
- Input sanitization and validation
- Nonce verification for all AJAX requests
- Capability checks for user permissions
- Content sanitization before applying to WordPress
- Safe handling of user-generated prompts

## 🌍 Internationalization

The plugin is translation-ready and includes support for multiple languages. Help us translate it to your language!

## 🤝 Contributing

We welcome contributions from the community! Whether you're fixing bugs, adding features, improving documentation, or translating the plugin, your help makes WP AI Assistant better for everyone.

### How to Contribute

1. **Report Issues**: Found a bug or have a feature request? Open an issue on GitHub
2. **Submit Pull Requests**: 
   - Fork the repository
   - Create a feature branch (`git checkout -b feature/amazing-feature`)
   - Commit your changes (`git commit -m 'Add some amazing feature'`)
   - Push to the branch (`git push origin feature/amazing-feature`)
   - Open a Pull Request

3. **Improve Documentation**: Help us make the documentation clearer and more comprehensive
4. **Translate**: Contribute translations to make the plugin accessible to more users
5. **Test**: Help us test new features and report any issues you find

### Development Guidelines

- Follow WordPress coding standards
- Write clear, commented code
- Add appropriate security checks
- Test your changes thoroughly
- Update documentation as needed

### Areas Where We Need Help

- 🐛 **Bug Fixes**: Help us squash bugs and improve stability
- ✨ **New Features**: Propose and implement new functionality
- 📚 **Documentation**: Improve guides, code comments, and examples
- 🌐 **Translations**: Translate the plugin to more languages
- 🧪 **Testing**: Test on different WordPress versions and configurations
- 🎨 **UI/UX Improvements**: Enhance the user interface and experience
- ⚡ **Performance**: Optimize code for better performance
- 🔌 **Integration**: Add support for more AI providers or WordPress plugins

### Code of Conduct

We are committed to providing a welcoming and inclusive environment. Please be respectful and constructive in all interactions.

## 📝 License

This plugin is licensed under the GPL v2 or later.

```
Copyright (C) 2024 Mohammad Yazdani

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## 👨‍💻 Author

**Mohammad Yazdani**
- Website: [aryatehran.com](https://aryatehran.com)
- Plugin URI: [https://aryatehran.com](https://aryatehran.com)

## 🙏 Acknowledgments

Thank you to all contributors, testers, and users who help make WP AI Assistant better every day. Your feedback, bug reports, and contributions are invaluable!

---

**Made with ❤️ for the WordPress community**

If you find this plugin useful, please consider:
- ⭐ Starring the repository
- 🐛 Reporting bugs
- 💡 Suggesting new features
- 🤝 Contributing code
- 📢 Sharing with others

Together, we can make WordPress content creation more powerful and accessible!

