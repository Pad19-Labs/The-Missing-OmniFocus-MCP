# Homebrew formula template — belongs in a tap repo (e.g. Pad19-Labs/homebrew-tap)
# once the project is public and a release exists. Fill in the version and the
# sha256 for each binary (shasum -a 256 <file>).
class MissingOmnifocusMcp < Formula
  desc "Full read/write access to OmniFocus for AI agents over MCP"
  homepage "https://github.com/Pad19-Labs/The-Missing-OmniFocus-MCP"
  version "0.1.0"

  on_arm do
    url "https://github.com/Pad19-Labs/The-Missing-OmniFocus-MCP/releases/download/v#{version}/missing-omnifocus-mcp-macos-arm64"
    sha256 "REPLACE_WITH_ARM64_SHA256"
  end

  on_intel do
    url "https://github.com/Pad19-Labs/The-Missing-OmniFocus-MCP/releases/download/v#{version}/missing-omnifocus-mcp-macos-x86_64"
    sha256 "REPLACE_WITH_X86_64_SHA256"
  end

  def install
    binary = Dir["missing-omnifocus-mcp-macos-*"].first
    bin.install binary => "missing-omnifocus-mcp"
  end

  def caveats
    <<~EOS
      Requires OmniFocus 4 Pro running on this Mac.

      Connect Claude Code:
        claude mcp add --scope user omnifocus -- missing-omnifocus-mcp mcp:start omnifocus

      Data (auth token, audit log) lives in:
        ~/Library/Application Support/MissingOmniFocusMCP
    EOS
  end

  test do
    assert_match "Laravel", shell_output("#{bin}/missing-omnifocus-mcp --version")
  end
end
