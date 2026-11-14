# Homebrew formula for Rift. This repository is its own tap:
#
#   brew tap lucapipolo/rift https://github.com/LucaPipolo/rift
#   brew trust lucapipolo/rift
#   brew install rift
#
# The release workflow sets the version and the four checksums on every stable
# release, so edit everything else here and leave those to it.
class Rift < Formula
  desc "Run Git worktrees side by side, each with its own containers and HTTPS domain"
  homepage "https://github.com/LucaPipolo/rift"
  version "1.2.0"
  license "MIT"

  # The binaries embed their own PHP runtime, so nothing here depends on
  # Homebrew's php: a PHP major bump can never break an installed Rift.
  on_macos do
    on_arm do
      url "https://github.com/LucaPipolo/rift/releases/download/v#{version}/rift-darwin-arm64"
      sha256 "75981fd1a0d9b2db0ad754ff233cf149bf24b416b3f657065faab0c8c7b280b9"
    end

    on_intel do
      url "https://github.com/LucaPipolo/rift/releases/download/v#{version}/rift-darwin-x86_64"
      sha256 "04e9f0c081d936d4ac0f051f47868e5a604c06a71a60b0a6f142ae5d656761cf"
    end
  end

  on_linux do
    on_arm do
      url "https://github.com/LucaPipolo/rift/releases/download/v#{version}/rift-linux-arm64"
      sha256 "bd9f49fc280ed80de807a221e59b25fbdaf71b813557bb188a2f14db5f054eff"
    end

    on_intel do
      url "https://github.com/LucaPipolo/rift/releases/download/v#{version}/rift-linux-x86_64"
      sha256 "001109c1ccd55bf0309507ff02bfe42b4df603dd30ce612ec2e17027c614c357"
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
