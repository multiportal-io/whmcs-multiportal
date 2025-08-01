# Contributing to WHMCS MultiPortal Module

We love your input! We want to make contributing to this project as easy and transparent as possible, whether it's:

- Reporting a bug
- Discussing the current state of the code
- Submitting a fix
- Proposing new features
- Becoming a maintainer

## Any contributions you make will be under the MIT Software License

In short, when you submit code changes, your submissions are understood to be under the same [MIT License](LICENSE) that covers the project. Feel free to contact the maintainers if that's a concern.

## Report bugs using Github's [issues](https://github.com/yourusername/whmcs-multiportal/issues)

We use GitHub issues to track public bugs. Report a bug by [opening a new issue](https://github.com/yourusername/whmcs-multiportal/issues/new); it's that easy!

## Write bug reports with detail, background, and sample code

**Great Bug Reports** tend to have:

- A quick summary and/or background
- Steps to reproduce
  - Be specific!
  - Give sample code if you can
- What you expected would happen
- What actually happens
- Notes (possibly including why you think this might be happening, or stuff you tried that didn't work)

## Development Process

1. **Setup Development Environment**

   ```bash
   git clone https://github.com/yourusername/whmcs-multiportal.git
   cd whmcs-multiportal
   ```

2. **Create a Feature Branch**

   ```bash
   git checkout -b feature/your-feature-name
   ```

3. **Make Your Changes**
   - Follow the existing code style
   - Add comments for complex logic
   - Update documentation as needed

4. **Test Your Changes**
   - Test in a WHMCS development environment
   - Ensure all existing functionality still works
   - Add unit tests for new features

5. **Commit Your Changes**

   ```bash
   git add .
   git commit -m "Add your meaningful commit message"
   ```

6. **Push to Your Fork**

   ```bash
   git push origin feature/your-feature-name
   ```

7. **Create a Pull Request**
   - Go to the original repository
   - Click "New Pull Request"
   - Select your fork and branch
   - Fill in the PR template
   - Submit!

## Code Style Guidelines

- **PHP**: Follow PSR-12 coding standards
- **Indentation**: Use 4 spaces (no tabs)
- **Line Length**: Keep lines under 120 characters
- **Comments**: Use PHPDoc for functions and classes
- **Naming**: Use descriptive variable and function names
- **Security**: Always validate input and escape output

## Testing

- Write unit tests for new functionality
- Ensure all tests pass before submitting PR
- Test in multiple WHMCS versions if possible
- Include integration tests for API calls

## Security

- Never commit credentials or API keys
- Report security vulnerabilities privately to maintainers
- Follow WHMCS security best practices
- Validate all user input
- Escape all output

## License

By contributing, you agree that your contributions will be licensed under its MIT License.