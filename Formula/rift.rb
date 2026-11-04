# Homebrew formula for Rift. This repository is its own tap:
#
#   brew tap lucapipolo/rift https://github.com/LucaPipolo/rift
#   brew install rift
#
# The release workflow sets the version and the four checksums on every stable
# release, so edit everything else here and leave those to it.
class Rift < Formula
  desc "Run Git worktrees side by side, each with its own containers and HTTPS domain"
  homepage "https://github.com/LucaPipolo/rift"
  version "1.0.0"
  license "MIT"

  # The binaries embed their own PHP runtime, so nothing here depends on
  # Homebrew's php: a PHP major bump can never break an installed Rift.
  on_macos do
    on_arm do
      url "https://github.com/LucaPipolo/rift/releases/download/v#{version}/rift-darwin-arm64"
      sha256 "26a617b3991a06d69abafb9dead39c9c46133cde4abb70454d2a1cd5bd705413"
    end

    on_intel do
      url "https://github.com/LucaPipolo/rift/releases/download/v#{version}/rift-darwin-x86_64"
      sha256 "ddf7d6dd05c41b709f533db1e6963b25d0dc13f531dd7220db2c85b0fc487bae"
    end
  end

  on_linux do
    on_arm do
      url "https://github.com/LucaPipolo/rift/releases/download/v#{version}/rift-linux-arm64"
      sha256 "03e06e01af005a84347a2ca1cac007133cd8d822563adc76bf677c4a56799a01"
    end

    on_intel do
      url "https://github.com/LucaPipolo/rift/releases/download/v#{version}/rift-linux-x86_64"
      sha256 "e945110838dfdac4fa17e639a31276e235c6ba4979203638d228ee832bfa5130"
    end
  end

  def install
    bin.install Dir["*"].first => "rift"
  end

  def caveats
    <<~EOS
      Rift needs Docker running, and *.test resolving to your machine.

      It serves each checkout over HTTPS with a certificate authority it
      creates itself, so the first `rift up` asks for your password once to
      trust it. One router serves every project, so you are asked once per
      machine, not once per project.
    EOS
  end

  test do
    assert_match "Rift", shell_output("#{bin}/rift --version")
  end
end
